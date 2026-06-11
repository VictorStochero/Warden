<?php

namespace VictorStochero\Warden\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use VictorStochero\Warden\Console\Audit\AdvisoryFormat;
use VictorStochero\Warden\Console\Audit\ComposerAudit;
use VictorStochero\Warden\Console\Audit\Remediation;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Json;
use VictorStochero\Warden\Warden;

/**
 * Child-side dependency audit: audits composer + npm dependencies and ships a
 * single `security` snapshot event through the normal pipeline (outbox -> ship
 * -> ingest) so the parent can display vulnerabilities.
 *
 * Composer auditing is portable across hosts (see {@see ComposerAudit}): it
 * uses the native binary when reachable and falls back to a binary-free audit
 * via the Packagist advisories API otherwise, recording a reason when it can't
 * run at all. One event per run (a snapshot, not a stream); the parent shows
 * the latest. Run it from cron/scheduler for continuous coverage.
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

        /** @var array<string, array{ran: bool, method: string|null, reason: string|null}> $tools */
        $tools = [];

        if (! $onlyNpm) {
            $composer = (new ComposerAudit($warden, base_path()))->run();
            $advisories = array_merge($advisories, $composer['advisories']);
            $tools['composer'] = $composer['status'];
        }

        if (! $onlyComposer) {
            $npm = $this->npmAudit();
            $advisories = array_merge($advisories, $npm['advisories']);
            $tools['npm'] = $npm['status'];
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
     * @return array{advisories: list<array<string, mixed>>, status: array{ran: bool, method: string|null, reason: string|null}}
     */
    protected function npmAudit(): array
    {
        if (! file_exists(base_path('package.json'))) {
            return ['advisories' => [], 'status' => $this->status(false, null, 'no_package_json')];
        }

        $result = Process::path(base_path())->run('npm audit --json');
        $json = Json::decode($result->output());

        if (! isset($json['vulnerabilities']) || ! is_array($json['vulnerabilities'])) {
            return ['advisories' => [], 'status' => $this->status(false, null, 'npm_not_found')];
        }

        $out = [];
        foreach ($json['vulnerabilities'] as $name => $vuln) {
            $vuln = Cast::arr($vuln);
            $title = '';
            $link = null;
            foreach (Cast::arr($vuln['via'] ?? null) as $via) {
                if (is_array($via)) {
                    $title = Cast::str($via['title'] ?? '');
                    $link = AdvisoryFormat::link($via['url'] ?? null);
                    break;
                }
            }

            $out[] = [
                'ecosystem' => 'npm',
                'package' => Cast::str($vuln['name'] ?? $name),
                'severity' => AdvisoryFormat::severity(Cast::str($vuln['severity'] ?? 'unknown')),
                'title' => $title,
                'cve' => null,
                'link' => $link,
                'affected' => Cast::str($vuln['range'] ?? ''),
                'fix' => Remediation::fromNpm($vuln['fixAvailable'] ?? null),
            ];
        }

        return ['advisories' => $out, 'status' => $this->status(true, 'binary', null)];
    }

    /** @return array{ran: bool, method: string|null, reason: string|null} */
    protected function status(bool $ran, ?string $method, ?string $reason): array
    {
        return ['ran' => $ran, 'method' => $method, 'reason' => $reason];
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
     * @param  array<string, array{ran: bool, method: string|null, reason: string|null}>  $tools
     * @param  array<string, int>  $counts
     */
    protected function summary(array $tools, array $counts, int $total): void
    {
        $this->newLine();
        foreach ($tools as $tool => $status) {
            if ($status['ran']) {
                $detail = $status['method'] === 'packagist' ? 'ran (packagist API)' : 'ran';
            } else {
                $detail = '<fg=yellow>skipped: '.($status['reason'] ?? 'unavailable').'</>';
            }
            $this->components->twoColumnDetail($tool, $detail);
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
