<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Json;
use VictorStochero\Warden\Warden;

/**
 * Child-side dependency audit: runs `composer audit` and `npm audit`, normalizes
 * the advisories and ships a single `security` snapshot event through the normal
 * pipeline (outbox -> ship -> ingest) so the parent can display vulnerabilities.
 *
 * One event per run (a snapshot, not a stream); the parent shows the latest.
 * Run it from cron/scheduler for continuous coverage, e.g. daily.
 */
class AuditCommand extends Command
{
    protected $signature = 'warden:audit
        {--composer : Only run composer audit}
        {--npm : Only run npm audit}';

    protected $description = 'Audit composer/npm dependencies and ship vulnerabilities to the parent (child)';

    public function handle(Warden $warden): int
    {
        if (! $warden->isChild()) {
            $this->components->error('warden:audit only runs in child mode.');

            return self::FAILURE;
        }

        if (! $warden->isChildConfigured()) {
            $this->components->error('Child is not configured (set WARDEN_PARENT_URL and WARDEN_TOKEN).');

            return self::FAILURE;
        }

        $onlyComposer = (bool) $this->option('composer');
        $onlyNpm = (bool) $this->option('npm');

        /** @var list<array<string, mixed>> $advisories */
        $advisories = [];
        $tools = [];

        if (! $onlyNpm) {
            [$found, $ran] = $this->composerAudit();
            $advisories = array_merge($advisories, $found);
            $tools['composer'] = $ran;
        }

        if (! $onlyComposer) {
            [$found, $ran] = $this->npmAudit();
            $advisories = array_merge($advisories, $found);
            $tools['npm'] = $ran;
        }

        $counts = $this->countBySeverity($advisories);

        $warden->reset();
        $warden->startTrace('command', name: 'warden:audit');
        $warden->keep();
        $warden->record('security', [
            'generated_at' => now()->toIso8601String(),
            'tools' => $tools,
            'counts' => $counts,
            'total' => count($advisories),
            'advisories' => array_slice($advisories, 0, 200),
        ]);
        $warden->flush();

        $this->summary($tools, $counts, count($advisories));

        return self::SUCCESS;
    }

    /**
     * Run `composer audit`, trying a list of candidate composer binaries and
     * using the first that yields parseable JSON. Daemons frequently run with a
     * PATH that lacks `composer`, so falling back to `./composer.phar` (or a
     * configured `composer_bin`) keeps composer auditing working in production.
     *
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    protected function composerAudit(): array
    {
        foreach ($this->composerCommands() as $cmd) {
            $result = Process::path(base_path())->run($cmd.' audit --format=json --no-interaction');
            $json = Json::decode($result->output());

            if (! isset($json['advisories']) || ! is_array($json['advisories'])) {
                continue;
            }

            $out = [];
            foreach ($json['advisories'] as $package => $list) {
                foreach (Cast::arr($list) as $a) {
                    $a = Cast::arr($a);
                    $out[] = [
                        'ecosystem' => 'composer',
                        'package' => Cast::str($a['packageName'] ?? $package),
                        'severity' => $this->normalizeSeverity(Cast::str($a['severity'] ?? 'unknown')),
                        'title' => Cast::str($a['title'] ?? ''),
                        'cve' => Cast::str($a['cve'] ?? '') ?: null,
                        'link' => $this->safeLink($a['link'] ?? null),
                        'affected' => Cast::str($a['affectedVersions'] ?? ''),
                    ];
                }
            }

            return [$out, true];
        }

        return [[], false];
    }

    /**
     * Composer invocation candidates, in priority order: a configured binary,
     * `composer` on PATH, a local `composer.phar` (only if present), then a
     * bare `composer.phar`.
     *
     * @return list<string>
     */
    protected function composerCommands(): array
    {
        $cmds = [];

        $bin = Cast::str(config('warden.child.audit.composer_bin'));
        if ($bin !== '') {
            $cmds[] = $bin;
        }

        $cmds[] = 'composer';

        $phar = base_path('composer.phar');
        if (is_file($phar)) {
            $cmds[] = 'php '.escapeshellarg($phar);
        }

        $cmds[] = 'composer.phar';

        return $cmds;
    }

    /** @return array{0: list<array<string, mixed>>, 1: bool} */
    protected function npmAudit(): array
    {
        if (! file_exists(base_path('package.json'))) {
            return [[], false];
        }

        $result = Process::path(base_path())->run('npm audit --json');
        $json = Json::decode($result->output());

        if (! isset($json['vulnerabilities']) || ! is_array($json['vulnerabilities'])) {
            return [[], false];
        }

        $out = [];
        foreach ($json['vulnerabilities'] as $name => $vuln) {
            $vuln = Cast::arr($vuln);
            $title = '';
            $link = null;
            foreach (Cast::arr($vuln['via'] ?? null) as $via) {
                if (is_array($via)) {
                    $title = Cast::str($via['title'] ?? '');
                    $link = $this->safeLink($via['url'] ?? null);
                    break;
                }
            }

            $out[] = [
                'ecosystem' => 'npm',
                'package' => Cast::str($vuln['name'] ?? $name),
                'severity' => $this->normalizeSeverity(Cast::str($vuln['severity'] ?? 'unknown')),
                'title' => $title,
                'cve' => null,
                'link' => $link,
                'affected' => Cast::str($vuln['range'] ?? ''),
            ];
        }

        return [$out, true];
    }

    /**
     * Keep an advisory link only when it is a real http(s) URL. Audit tooling
     * output is untrusted (a compromised child could craft it); a `javascript:`
     * or `data:` scheme rendered into the parent dashboard would be a stored
     * XSS, so anything that is not http(s) is dropped to null at ingestion.
     */
    protected function safeLink(mixed $link): ?string
    {
        $link = Cast::str($link);

        if ($link === '' || ! Str::startsWith(strtolower($link), ['http://', 'https://'])) {
            return null;
        }

        return $link;
    }

    protected function normalizeSeverity(string $severity): string
    {
        $severity = strtolower(trim($severity));

        return match ($severity) {
            'critical' => 'critical',
            'high' => 'high',
            'moderate', 'medium' => 'moderate',
            'low' => 'low',
            'info', 'none' => 'info',
            default => 'unknown',
        };
    }

    /**
     * @param  list<array<string, mixed>>  $advisories
     * @return array<string, int>
     */
    protected function countBySeverity(array $advisories): array
    {
        $counts = [];
        foreach ($advisories as $a) {
            $sev = Cast::str($a['severity'] ?? 'unknown');
            $counts[$sev] = ($counts[$sev] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param  array<string, bool>  $tools
     * @param  array<string, int>  $counts
     */
    protected function summary(array $tools, array $counts, int $total): void
    {
        $this->newLine();
        foreach ($tools as $tool => $ran) {
            $this->components->twoColumnDetail($tool, $ran ? 'ran' : '<fg=yellow>skipped / unavailable</>');
        }
        $this->components->twoColumnDetail('Vulnerabilities', (string) $total);
        foreach ($counts as $sev => $n) {
            $this->components->twoColumnDetail("  {$sev}", (string) $n);
        }
        $this->newLine();
        $this->line('  <fg=gray>Shipped to the parent. Deliver now with</> <fg=yellow>php artisan warden:ship --once</><fg=gray>.</>');
        $this->newLine();
    }
}
