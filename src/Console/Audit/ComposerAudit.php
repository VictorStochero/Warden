<?php

namespace VictorStochero\Warden\Console\Audit;

use Closure;
use Illuminate\Support\Facades\Process;
use Throwable;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Json;
use VictorStochero\Warden\Warden;

/**
 * Portable composer audit. Tries, in order:
 *   Tier 0 — the native `composer audit` binary (richest fidelity), located
 *            robustly across hosts via {@see ComposerLocator};
 *   Tier 1 — a binary-free audit straight from `composer.lock` + the Packagist
 *            advisories API ({@see PackagistAudit}), for composer-less runtimes;
 *   Tier 2 — a diagnosed skip carrying a machine-readable reason.
 *
 * The runner is injectable so the orchestration is testable without spawning a
 * real composer process.
 */
class ComposerAudit
{
    /** @var Closure(string): string */
    protected Closure $runner;

    protected ComposerLocator $locator;

    protected PackagistAudit $packagist;

    /**
     * @param  (Closure(string): string)|null  $runner  given a command, returns its stdout
     */
    public function __construct(
        protected Warden $warden,
        protected string $basePath,
        ?ComposerLocator $locator = null,
        ?PackagistAudit $packagist = null,
        ?Closure $runner = null,
    ) {
        $this->locator = $locator ?? new ComposerLocator($basePath, Cast::str(config('warden.child.audit.composer_bin')));
        $this->packagist = $packagist ?? new PackagistAudit($warden);
        $this->runner = $runner ?? fn (string $cmd): string => Process::path($basePath)->run($cmd)->output();
    }

    /**
     * @return array{advisories: list<array<string, mixed>>, status: array{ran: bool, method: string|null, reason: string|null}}
     */
    public function run(): array
    {
        // Tier 0 — native binary.
        foreach ($this->locator->candidates() as $cmd) {
            $advisories = $this->parseComposerJson($this->safeRun($cmd.' audit --format=json --no-interaction'));

            if ($advisories !== null) {
                return ['advisories' => $advisories, 'status' => ['ran' => true, 'method' => 'binary', 'reason' => null]];
            }
        }

        // Tier 1 — binary-free, from the lock + Packagist.
        $lock = $this->readLock();

        if ($lock === null) {
            return ['advisories' => [], 'status' => ['ran' => false, 'method' => null, 'reason' => 'composer_not_found']];
        }

        $packagist = $this->packagist->run($lock);

        if ($packagist['ran']) {
            return ['advisories' => $packagist['advisories'], 'status' => ['ran' => true, 'method' => 'packagist', 'reason' => null]];
        }

        // Tier 2 — diagnosed skip.
        return ['advisories' => [], 'status' => ['ran' => false, 'method' => null, 'reason' => $packagist['reason'] ?? 'composer_not_found']];
    }

    protected function safeRun(string $command): string
    {
        try {
            return ($this->runner)($command);
        } catch (Throwable) {
            return '';
        }
    }

    /**
     * @return list<array<string, mixed>>|null null when the output isn't a composer audit document
     */
    protected function parseComposerJson(string $output): ?array
    {
        $json = $this->decodeLenient($output);

        if (! isset($json['advisories']) || ! is_array($json['advisories'])) {
            return null;
        }

        $out = [];

        foreach ($json['advisories'] as $package => $list) {
            foreach (Cast::arr($list) as $advisory) {
                $advisory = Cast::arr($advisory);
                $out[] = [
                    'ecosystem' => 'composer',
                    'package' => Cast::str($advisory['packageName'] ?? $package),
                    'severity' => AdvisoryFormat::severity(Cast::str($advisory['severity'] ?? 'unknown')),
                    'title' => Cast::str($advisory['title'] ?? ''),
                    'cve' => Cast::str($advisory['cve'] ?? '') ?: null,
                    'link' => AdvisoryFormat::link($advisory['link'] ?? null),
                    'affected' => Cast::str($advisory['affectedVersions'] ?? ''),
                ];
            }
        }

        return $out;
    }

    /**
     * Composer should emit clean JSON with --format=json, but plugins/platform
     * warnings occasionally prepend noise to stdout — tolerate a leading banner
     * by retrying from the first brace.
     *
     * @return array<string, mixed>
     */
    protected function decodeLenient(string $output): array
    {
        $json = Json::decode($output);

        if (isset($json['advisories'])) {
            return $json;
        }

        $start = strpos($output, '{');

        if ($start !== false) {
            $retry = Json::decode(substr($output, $start));

            if (isset($retry['advisories'])) {
                return $retry;
            }
        }

        return $json;
    }

    protected function readLock(): ?string
    {
        $path = $this->basePath.'/composer.lock';

        if (! is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);

        return is_string($contents) && $contents !== '' ? $contents : null;
    }
}
