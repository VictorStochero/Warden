<?php

namespace VictorStochero\Warden\Console\Audit;

use Illuminate\Support\Facades\Http;
use Throwable;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Json;
use VictorStochero\Warden\Support\VersionRange;
use VictorStochero\Warden\Warden;

/**
 * Binary-free composer audit (Tier 1): when no `composer` binary can be found —
 * the common case in a multi-stage Docker runtime or a PATH-stripped daemon —
 * derive advisories straight from `composer.lock` and the Packagist Security
 * Advisories API, over the same zero-dependency HTTP the child already uses to
 * ship. This is the same data source `composer audit` itself consults, so it
 * works on any host that has the lock file and outbound HTTPS.
 */
class PackagistAudit
{
    public const DEFAULT_URL = 'https://packagist.org/api/security-advisories/';

    public function __construct(protected Warden $warden) {}

    /**
     * @return array{advisories: list<array<string, mixed>>, ran: bool, reason: ?string}
     */
    public function run(string $lockContents): array
    {
        $packages = $this->packagesFromLock($lockContents);

        if ($packages === []) {
            return ['advisories' => [], 'ran' => false, 'reason' => 'lock_missing'];
        }

        // Our own outbound call — never let the HTTP recorder observe it (§18.3).
        $map = $this->warden->withoutRecording(fn () => $this->fetch(array_keys($packages)));

        if ($map === null) {
            return ['advisories' => [], 'ran' => false, 'reason' => 'network_error'];
        }

        $out = [];

        foreach ($map as $pkg => $list) {
            $installed = $packages[(string) $pkg] ?? null;

            if ($installed === null) {
                continue;
            }

            foreach (Cast::arr($list) as $advisory) {
                $advisory = Cast::arr($advisory);
                $affected = Cast::str($advisory['affectedVersions'] ?? '');

                if (! VersionRange::matches($installed, $affected)) {
                    continue;
                }

                $out[] = [
                    'ecosystem' => 'composer',
                    'package' => Cast::str($advisory['packageName'] ?? $pkg),
                    'severity' => AdvisoryFormat::severity(Cast::str($advisory['severity'] ?? 'unknown')),
                    'title' => Cast::str($advisory['title'] ?? ''),
                    'cve' => Cast::str($advisory['cve'] ?? '') ?: null,
                    'link' => AdvisoryFormat::link($advisory['link'] ?? null),
                    'affected' => $affected,
                    'fix' => Remediation::fromComposerConstraint($affected),
                ];
            }
        }

        return ['advisories' => $out, 'ran' => true, 'reason' => null];
    }

    /**
     * Installed packages (require + require-dev) as name => version.
     *
     * @return array<string, string>
     */
    protected function packagesFromLock(string $lockContents): array
    {
        $json = Json::decode($lockContents);

        $packages = [];

        foreach (['packages', 'packages-dev'] as $section) {
            foreach (Cast::arr($json[$section] ?? null) as $package) {
                $package = Cast::arr($package);
                $name = Cast::str($package['name'] ?? '');
                $version = Cast::str($package['version'] ?? '');

                if ($name !== '' && $version !== '') {
                    $packages[$name] = $version;
                }
            }
        }

        return $packages;
    }

    /**
     * @param  list<string>  $names
     * @return array<array-key, mixed>|null the advisories map, or null on failure
     */
    protected function fetch(array $names): ?array
    {
        try {
            $url = Cast::str(config('warden.child.audit.advisories_url', self::DEFAULT_URL), self::DEFAULT_URL);

            if ($url === '') {
                return null; // fallback explicitly disabled
            }

            $response = Http::asForm()
                ->timeout(max(1, Cast::int(config('warden.child.audit.timeout', 20), 20)))
                ->post($url, ['packages' => $names]);

            if (! $response->successful()) {
                return null;
            }

            $json = $response->json();

            if (! is_array($json) || ! isset($json['advisories']) || ! is_array($json['advisories'])) {
                return null;
            }

            return $json['advisories'];
        } catch (Throwable) {
            return null;
        }
    }
}
