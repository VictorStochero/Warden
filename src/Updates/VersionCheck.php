<?php

namespace VictorStochero\Warden\Updates;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use VictorStochero\Warden\Facades\Warden;
use VictorStochero\Warden\Models\Setting;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\PackageVersion;

/**
 * The new-version notice (parent only). A scheduled check asks Packagist for the
 * latest STABLE release of the package, caches the verdict in wdn_settings, and
 * the dashboard renders a discreet banner from that cache — the page render
 * never makes a network call. Everything is best-effort: a network failure
 * leaves the previous cache untouched and never throws into the host (RNF-2).
 */
class VersionCheck
{
    private const KEY = 'version_check';

    /**
     * Whether the check is on. Precedence mirrors the rest of Warden:
     * .env (WARDEN_VERSION_CHECK) wins, then the dashboard toggle (wdn_settings),
     * then the config default.
     */
    public function enabled(): bool
    {
        $default = Cast::bool(config('warden.parent.version_check.enabled', true));

        if (getenv('WARDEN_VERSION_CHECK') !== false) {
            return $default; // config already reflects the explicit .env value
        }

        $state = $this->state();

        return array_key_exists('enabled', $state) ? Cast::bool($state['enabled']) : $default;
    }

    /** Persist the dashboard toggle (ignored at read time when .env pins it). */
    public function setEnabled(bool $enabled): void
    {
        $this->merge(['enabled' => $enabled]);
    }

    /** Operator dismissed the banner for a specific version. */
    public function dismiss(string $version): void
    {
        $this->merge(['dismissed' => ltrim($version, 'vV')]);
    }

    /**
     * Run the check: fetch Packagist, pick the latest stable, cache the result.
     * Skips when disabled or when a fresh result already exists (unless forced).
     */
    public function run(bool $force = false): void
    {
        if (! $this->enabled()) {
            return;
        }

        Warden::withoutRecording(function () use ($force): void {
            try {
                if (! $force && $this->isFresh()) {
                    return;
                }

                $installed = PackageVersion::installed();
                $latest = $this->fetchLatest();

                $this->merge([
                    'current' => $installed,
                    'latest' => $latest,
                    'checked_at' => now()->toIso8601String(),
                ]);
            } catch (\Throwable) {
                // Best-effort: keep the previous cache, never break the host.
            }
        });
    }

    /**
     * The notice payload for the dashboard, or null when there's nothing to show
     * (disabled, no data, already on the latest, or the latest was dismissed).
     *
     * @return array{current: string, latest: string}|null
     */
    public function notice(): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        $state = $this->state();
        $current = Cast::str($state['current'] ?? '');
        $latest = Cast::str($state['latest'] ?? '');
        $dismissed = Cast::str($state['dismissed'] ?? '');

        if ($current === '' || $latest === '' || ! PackageVersion::isNewer($latest, $current)) {
            return null;
        }

        if ($dismissed !== '' && ! PackageVersion::isNewer($latest, $dismissed)) {
            return null; // dismissed this (or a newer-or-equal) version already
        }

        return ['current' => $current, 'latest' => $latest];
    }

    private function fetchLatest(): ?string
    {
        $url = Cast::str(config('warden.parent.version_check.url'));
        $timeout = Cast::int(config('warden.parent.version_check.timeout'), 10);
        $includePre = Cast::bool(config('warden.parent.version_check.include_prereleases', false));

        if ($url === '') {
            return null;
        }

        $response = Http::timeout($timeout)->acceptJson()->get($url);

        if (! $response->successful()) {
            return null;
        }

        $packages = Cast::arr($response->json('packages'));
        $releases = Cast::arr($packages[PackageVersion::PACKAGE] ?? null);

        $versions = [];
        foreach ($releases as $release) {
            $version = Cast::str(Cast::arr($release)['version'] ?? '');
            if ($version !== '') {
                $versions[] = $version;
            }
        }

        return PackageVersion::latest($versions, $includePre);
    }

    private function isFresh(): bool
    {
        $checkedAt = Cast::str($this->state()['checked_at'] ?? '');

        if ($checkedAt === '') {
            return false;
        }

        $ttlHours = Cast::int(config('warden.parent.version_check.ttl_hours'), 24);

        try {
            return now()->lessThan(Carbon::parse($checkedAt)->addHours($ttlHours));
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private function state(): array
    {
        $out = [];
        foreach (Cast::arr(Setting::read(self::KEY, [])) as $key => $value) {
            $out[Cast::str($key)] = $value;
        }

        return $out;
    }

    /** @param array<string, mixed> $patch */
    private function merge(array $patch): void
    {
        Setting::write(self::KEY, array_merge($this->state(), $patch));
    }
}
