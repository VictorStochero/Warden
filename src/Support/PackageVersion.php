<?php

namespace VictorStochero\Warden\Support;

use Composer\InstalledVersions;

/**
 * Pure version helpers for the new-version notice. Dependency-free on purpose
 * (RNF-3): "stable" is decided by a regex, not composer/semver, and comparison
 * uses PHP's native version_compare. The installed version comes from Composer's
 * generated runtime API, which is always present in an installed package.
 */
final class PackageVersion
{
    public const PACKAGE = 'victorstochero/warden';

    /** The installed package version (no leading "v"), or null if undeterminable. */
    public static function installed(): ?string
    {
        if (! class_exists(InstalledVersions::class)) {
            return null;
        }

        try {
            $version = InstalledVersions::getPrettyVersion(self::PACKAGE);
        } catch (\Throwable) {
            return null;
        }

        return is_string($version) && $version !== '' ? ltrim($version, 'vV') : null;
    }

    /**
     * A stable release is plain dotted digits (optionally a "+build" suffix):
     * "0.4.0" is stable; "0.4.0-beta.1", "dev-main" and "1.0.0-RC1" are not.
     */
    public static function isStable(string $version): bool
    {
        return (bool) preg_match('/^\d+(\.\d+)*(\+[0-9A-Za-z.-]+)?$/', ltrim($version, 'vV'));
    }

    /**
     * Highest version in the list, filtered to stable releases unless prereleases
     * are explicitly included. Returns null when nothing qualifies.
     *
     * @param  list<string>  $versions
     */
    public static function latest(array $versions, bool $includePrereleases = false): ?string
    {
        $best = null;

        foreach ($versions as $version) {
            $version = ltrim($version, 'vV');

            if ($version === '' || (! $includePrereleases && ! self::isStable($version))) {
                continue;
            }

            if ($best === null || version_compare($version, $best, '>')) {
                $best = $version;
            }
        }

        return $best;
    }

    public static function isNewer(string $candidate, string $current): bool
    {
        return version_compare(ltrim($candidate, 'vV'), ltrim($current, 'vV'), '>');
    }
}
