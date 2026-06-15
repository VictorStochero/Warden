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
 */
class HostRecorder extends AbstractRecorder
{
    protected static ?float $lastSample = null;

    /**
     * Previous /proc/stat snapshot for CPU delta.
     *
     * @var array{idle:int, total:int}|null
     */
    protected static ?array $lastCpu = null;

    public function type(): string
    {
        return 'host';
    }

    public function register(): void
    {
        $this->listen(RequestHandled::class, fn () => $this->sample());
        $this->listen(CommandStarting::class, fn () => $this->sample());
    }

    public function sample(): void
    {
        $interval = Cast::int($this->config->get('warden.child.host_interval', 15), 15);
        $now = microtime(true);

        if (self::$lastSample !== null && ($now - self::$lastSample) < $interval) {
            return;
        }
        self::$lastSample = $now;

        $this->record([
            'hostname' => gethostname() ?: 'unknown',
            'cpu' => $this->cpuPercent(),
            'memory' => $this->memory(),
            'load' => $this->load(),
            'disk' => $this->disk(),
        ]);
    }

    /** @return array{1:float,5:float,15:float}|null */
    protected function load(): ?array
    {
        if (! function_exists('sys_getloadavg')) {
            return null;
        }

        $load = sys_getloadavg();

        return [1 => $load[0] ?? 0.0, 5 => $load[1] ?? 0.0, 15 => $load[2] ?? 0.0];
    }

    /** @return array{total:int,available:int,used_percent:float}|null */
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

        return [
            'total' => $total,
            'available' => $available,
            'used_percent' => round((1 - $available / $total) * 100, 1),
        ];
    }

    protected function cpuPercent(): ?float
    {
        if (! is_readable('/proc/stat')) {
            return null;
        }

        $line = strtok((string) @file_get_contents('/proc/stat'), "\n");
        if ($line === false || ! str_starts_with($line, 'cpu ')) {
            return null;
        }

        $parts = array_map('intval', preg_split('/\s+/', trim($line)) ?: []);
        array_shift($parts); // "cpu"
        $idle = ($parts[3] ?? 0) + ($parts[4] ?? 0);
        $total = array_sum($parts);

        $previous = self::$lastCpu;
        self::$lastCpu = ['idle' => $idle, 'total' => $total];

        if ($previous === null || ($total - $previous['total']) <= 0) {
            return null; // need two samples
        }

        $idleDelta = $idle - $previous['idle'];
        $totalDelta = $total - $previous['total'];

        return round((1 - $idleDelta / $totalDelta) * 100, 1);
    }

    /** @return array{free:int,total:int,used_percent:float}|null */
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
            'used_percent' => round((1 - $free / $total) * 100, 1),
        ];
    }
}
