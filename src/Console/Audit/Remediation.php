<?php

namespace VictorStochero\Warden\Console\Audit;

use VictorStochero\Warden\Support\Cast;

/**
 * Derives a friendly "how to fix" hint for an advisory. Returns a structured
 * type + optional version so the dashboard can localize it (instead of baking
 * English text into the snapshot). Composer advisories carry a version
 * constraint (`affectedVersions`) whose upper bound is the fix; npm carries a
 * `fixAvailable` field.
 */
class Remediation
{
    /**
     * @return array{type: string, version: string|null}
     */
    public static function fromComposerConstraint(string $affected): array
    {
        $affected = trim($affected);

        if ($affected === '') {
            return self::result('unknown');
        }

        $bestVersion = null;
        $inclusive = false;
        $parsedAny = false;

        foreach (preg_split('/\s*\|\|?\s*/', $affected) ?: [] as $clause) {
            foreach (preg_split('/\s*,\s*|\s+/', trim($clause)) ?: [] as $term) {
                $term = trim($term);
                if ($term === '') {
                    continue;
                }

                if (preg_match('/^(<=|<)\s*v?(\d+(?:\.\d+)*(?:[.-][A-Za-z0-9]+)*)$/', $term, $m)) {
                    $parsedAny = true;
                    $version = $m[2];

                    if ($bestVersion === null || version_compare($version, $bestVersion, '>')) {
                        $bestVersion = $version;
                        $inclusive = $m[1] === '<=';
                    }
                } elseif (preg_match('/^(>=|>|=|==|!=|<>)?\s*v?\d/', $term)) {
                    $parsedAny = true; // a parseable lower bound / pin, but not an upper bound
                }
            }
        }

        if ($bestVersion !== null) {
            return self::result($inclusive ? 'upgrade_above' : 'upgrade', $bestVersion);
        }

        // Parseable but with no upper bound = open-ended range, no fix published yet.
        return $parsedAny ? self::result('none') : self::result('unknown');
    }

    /**
     * @return array{type: string, version: string|null}
     */
    public static function fromNpm(mixed $fixAvailable): array
    {
        if (is_array($fixAvailable)) {
            $version = Cast::str($fixAvailable['version'] ?? '');

            return $version !== '' ? self::result('upgrade', $version) : self::result('fix_available');
        }

        if ($fixAvailable === true) {
            return self::result('fix_available');
        }

        if ($fixAvailable === false) {
            return self::result('none');
        }

        return self::result('unknown');
    }

    /**
     * @return array{type: string, version: string|null}
     */
    private static function result(string $type, ?string $version = null): array
    {
        return ['type' => $type, 'version' => $version];
    }
}
