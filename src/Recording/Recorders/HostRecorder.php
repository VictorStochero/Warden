<?php

namespace VictorStochero\Warden\Recording\Recorders;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Http\Events\RequestHandled;
use VictorStochero\Warden\Recording\AbstractRecorder;
use VictorStochero\Warden\Support\Cast;

/**
 * Host metrics from /proc (Linux). Not a per-event listener — it samples at
 * most once per `host_interval` seconds, piggy-backing on request/command
 * boundaries so it never adds a separate process. Falls back gracefully on
 * non-Linux platforms (load + disk only).
 *
 * CPU% needs the delta between two /proc/stat snapshots, and under PHP-FPM
 * every request starts with fresh statics — so the previous snapshot (and the
 * sampling throttle) live in a small state file in the system temp dir, shared
 * across processes. The same file carries per-pid CPU ticks so the top-process
 * list can report instantaneous CPU, not lifetime averages. Everything here is
 * best-effort: an unreadable /proc or unwritable temp dir degrades to nulls,
 * never into the host app (RNF-2).
 */
class HostRecorder extends AbstractRecorder
{
    /** Linux USER_HZ; the kernel reports ticks at 100/s on every mainstream build. */
    protected const CLOCK_TICKS = 100;

    /** Fallback page size (bytes) for rss→bytes; 4 KiB on all mainstream Linux. */
    protected const PAGE_SIZE = 4096;

    /** How many processes the payload carries (top CPU ∪ top memory). */
    protected const TOP_PROCESSES = 8;

    /** In-process fallbacks when the cross-process state file is unavailable. */
    protected static ?float $lastSample = null;

    /** @var array<string, mixed>|null */
    protected static ?array $lastState = null;

    public function type(): string
    {
        return 'host';
    }

    public function register(): void
    {
        $this->events->listen(RequestHandled::class, fn () => $this->sample());
        $this->events->listen(CommandStarting::class, fn () => $this->sample());
    }

    public function sample(): void
    {
        try {
            $this->doSample();
        } catch (\Throwable) {
            // Host sampling must never break the host app (RNF-2).
        }
    }

    protected function doSample(): void
    {
        $interval = Cast::int($this->config->get('warden.child.host_interval', 15), 15);
        $now = microtime(true);

        $state = $this->loadState() ?? self::$lastState;
        $lastAt = $state !== null ? Cast::float($state['sampled_at'] ?? null) : self::$lastSample;

        if ($lastAt !== null && $lastAt > 0.0 && ($now - $lastAt) < $interval) {
            return;
        }
        self::$lastSample = $now;

        $stat = $this->readStat();
        $procs = $this->readProcesses();

        $prevCpu = $state !== null ? $this->snapshotFrom($state) : null;
        $elapsed = ($lastAt !== null && $lastAt > 0.0) ? max(0.0, $now - $lastAt) : null;
        $prevProcs = $state !== null ? array_map(Cast::int(...), Cast::arr($state['procs'] ?? [])) : [];

        $this->record([
            'hostname' => gethostname() ?: 'unknown',
            'cpu' => $this->cpuPercent($stat['cpu'] ?? null, $prevCpu),
            'cores' => $stat['cores'] ?? null,
            'memory' => $this->memory(),
            'load' => $this->load(),
            'disk' => $this->disk(),
            'processes' => $procs !== null ? $this->topProcesses($procs, $prevProcs, $elapsed) : null,
        ]);

        $ticks = [];
        foreach ($procs ?? [] as $proc) {
            $ticks[$proc['pid']] = $proc['ticks'];
        }

        $newState = [
            'sampled_at' => $now,
            'cpu' => $stat['cpu'] ?? null,
            'procs' => $ticks,
        ];

        $this->saveState($newState);
        self::$lastState = $newState;
    }

    // ----------------------------------------------------------------- cpu

    /**
     * Aggregate CPU% across all cores from the delta between this and the
     * previous snapshot. Null when /proc is unavailable or no previous snapshot
     * exists yet (the very first sample on a host).
     *
     * @param  array{idle:int, total:int}|null  $current
     * @param  array{idle:int, total:int}|null  $previous
     */
    protected function cpuPercent(?array $current, ?array $previous): ?float
    {
        if ($current === null || $previous === null) {
            return null;
        }

        $totalDelta = $current['total'] - $previous['total'];

        if ($totalDelta <= 0) {
            return null;
        }

        $idleDelta = $current['idle'] - $previous['idle'];

        return round(max(0.0, min(100.0, (1 - $idleDelta / $totalDelta) * 100)), 1);
    }

    /**
     * One read of /proc/stat: the aggregate jiffies snapshot plus core count.
     *
     * @return array{cpu: array{idle:int, total:int}|null, cores: int|null}|null
     */
    protected function readStat(): ?array
    {
        if (! is_readable('/proc/stat')) {
            return null;
        }

        $contents = (string) @file_get_contents('/proc/stat');
        $lines = explode("\n", $contents);
        $first = $lines[0];

        $cpu = null;
        if (str_starts_with($first, 'cpu ')) {
            $parts = array_map('intval', preg_split('/\s+/', trim($first)) ?: []);
            array_shift($parts); // "cpu"
            $idle = ($parts[3] ?? 0) + ($parts[4] ?? 0);
            $cpu = ['idle' => $idle, 'total' => array_sum($parts)];
        }

        $cores = count(preg_grep('/^cpu\d+\s/', $lines) ?: []);

        return ['cpu' => $cpu, 'cores' => $cores > 0 ? $cores : null];
    }

    // ------------------------------------------------------------ processes

    /**
     * One pass over /proc/[pid]/stat. The comm field is parenthesised and may
     * itself contain spaces/parens, so the line is split at the *last* ')'.
     * Only the 15-char executable name is captured — never the command line,
     * which can carry secrets in argv.
     *
     * @return list<array{pid:int, name:string, ticks:int, memory:int, start:int}>|null
     */
    protected function readProcesses(): ?array
    {
        if (! @is_dir('/proc')) {
            return null;
        }

        $out = [];

        foreach (glob('/proc/[0-9]*', GLOB_NOSORT) ?: [] as $dir) {
            $stat = @file_get_contents($dir.'/stat');

            if ($stat === false) {
                continue; // process exited or not ours to read
            }

            $open = strpos($stat, '(');
            $close = strrpos($stat, ')');

            if ($open === false || $close === false || $close < $open) {
                continue;
            }

            // Fields after comm, 0-indexed from kernel field 3 (state):
            // utime=11, stime=12, starttime=19, rss=21.
            $rest = preg_split('/\s+/', trim(substr($stat, $close + 1))) ?: [];

            $out[] = [
                'pid' => (int) substr($stat, 0, $open),
                'name' => substr($stat, $open + 1, $close - $open - 1),
                'ticks' => (int) ($rest[11] ?? 0) + (int) ($rest[12] ?? 0),
                'memory' => (int) ($rest[21] ?? 0) * self::PAGE_SIZE,
                'start' => (int) ($rest[19] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * The processes eating resources: top CPU ∪ top memory, deduplicated.
     * CPU% is the tick delta against the previous sample when the pid was seen
     * then (instantaneous, like top); otherwise the lifetime average (like ps).
     * Percent is of a single core, so a busy multi-threaded process can exceed 100.
     *
     * @param  list<array{pid:int, name:string, ticks:int, memory:int, start:int}>  $procs
     * @param  array<array-key, int>  $prevTicks
     * @return list<array{pid:int, name:string, cpu:float|null, memory:int}>
     */
    protected function topProcesses(array $procs, array $prevTicks, ?float $elapsed): array
    {
        $uptime = $this->uptime();

        $scored = array_map(function (array $proc) use ($prevTicks, $elapsed, $uptime): array {
            $prev = $prevTicks[$proc['pid']] ?? null;

            if ($prev !== null && $elapsed !== null && $elapsed > 0 && $proc['ticks'] >= $prev) {
                $cpu = ($proc['ticks'] - $prev) / ($elapsed * self::CLOCK_TICKS) * 100;
            } elseif ($uptime !== null) {
                $age = max(1.0, $uptime - $proc['start'] / self::CLOCK_TICKS);
                $cpu = ($proc['ticks'] / self::CLOCK_TICKS) / $age * 100;
            } else {
                $cpu = null;
            }

            return [
                'pid' => $proc['pid'],
                'name' => $proc['name'],
                'cpu' => $cpu !== null ? round($cpu, 1) : null,
                'memory' => $proc['memory'],
            ];
        }, $procs);

        $byCpu = $scored;
        usort($byCpu, fn (array $a, array $b): int => ($b['cpu'] ?? -1) <=> ($a['cpu'] ?? -1));

        $byMem = $scored;
        usort($byMem, fn (array $a, array $b): int => $b['memory'] <=> $a['memory']);

        $top = [];
        foreach ([...array_slice($byCpu, 0, 5), ...array_slice($byMem, 0, 5)] as $proc) {
            $top[$proc['pid']] ??= $proc;
        }

        return array_slice(array_values($top), 0, self::TOP_PROCESSES);
    }

    protected function uptime(): ?float
    {
        if (! is_readable('/proc/uptime')) {
            return null;
        }

        $raw = (string) @file_get_contents('/proc/uptime');
        $seconds = (float) strtok($raw, ' ');

        return $seconds > 0.0 ? $seconds : null;
    }

    // ------------------------------------------------------- memory / disk

    /** @return array{1:float,5:float,15:float}|null */
    protected function load(): ?array
    {
        if (! function_exists('sys_getloadavg')) {
            return null;
        }

        $load = sys_getloadavg();

        if ($load === false) {
            return null;
        }

        return [1 => $load[0], 5 => $load[1], 15 => $load[2]];
    }

    /** @return array<string, int|float|null>|null */
    protected function memory(): ?array
    {
        if (! is_readable('/proc/meminfo')) {
            return null;
        }

        $info = [];
        foreach (explode("\n", (string) @file_get_contents('/proc/meminfo')) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)/', $line, $m)) {
                $info[$m[1]] = (int) $m[2] * 1024; // kB -> bytes
            }
        }

        $total = $info['MemTotal'] ?? 0;
        $available = $info['MemAvailable'] ?? ($info['MemFree'] ?? 0);

        if ($total === 0) {
            return null;
        }

        $swapTotal = $info['SwapTotal'] ?? 0;
        $swapFree = $info['SwapFree'] ?? 0;

        return [
            'total' => $total,
            'available' => $available,
            'used' => max(0, $total - $available),
            'free' => $info['MemFree'] ?? null,
            'used_percent' => round((1 - $available / $total) * 100, 1),
            'swap_total' => $swapTotal,
            'swap_used' => max(0, $swapTotal - $swapFree),
            'swap_used_percent' => $swapTotal > 0 ? round((1 - $swapFree / $swapTotal) * 100, 1) : null,
        ];
    }

    /** @return array{free:int,total:int,used:int,used_percent:float}|null */
    protected function disk(): ?array
    {
        $total = @disk_total_space('/');
        $free = @disk_free_space('/');

        if (! $total || $free === false) {
            return null;
        }

        return [
            'free' => (int) $free,
            'total' => (int) $total,
            'used' => max(0, (int) $total - (int) $free),
            'used_percent' => round((1 - $free / $total) * 100, 1),
        ];
    }

    // ---------------------------------------------------------------- state

    /**
     * Cross-process snapshot store: previous /proc/stat jiffies, per-pid ticks
     * and the throttle timestamp. Keyed by install dir so co-hosted apps don't
     * share a file. CLI and FPM may run as different users — a permission
     * failure on either side just degrades to the in-process statics.
     */
    protected function statePath(): string
    {
        return sys_get_temp_dir().'/warden-host-'.sha1(__DIR__).'.json';
    }

    /** @return array<array-key, mixed>|null */
    protected function loadState(): ?array
    {
        $raw = @file_get_contents($this->statePath());

        if ($raw === false || $raw === '') {
            return null;
        }

        $state = json_decode($raw, true);

        return is_array($state) ? $state : null;
    }

    /** @param array<string, mixed> $state */
    protected function saveState(array $state): void
    {
        $path = $this->statePath();
        $existed = @file_exists($path);

        if (@file_put_contents($path, json_encode($state), LOCK_EX) !== false && ! $existed) {
            @chmod($path, 0600);
        }
    }

    /** @return array{idle:int, total:int}|null */
    protected function snapshotFrom(mixed $state): ?array
    {
        $cpu = Cast::arr(Cast::arr($state)['cpu'] ?? null);

        if (! isset($cpu['idle'], $cpu['total'])) {
            return null;
        }

        return ['idle' => Cast::int($cpu['idle']), 'total' => Cast::int($cpu['total'])];
    }
}
