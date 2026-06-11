<?php

namespace VictorStochero\Warden\Console\Audit;

use Illuminate\Support\Str;
use VictorStochero\Warden\Support\Cast;

/**
 * Shared normalization for advisories, used by both audit paths (the native
 * `composer audit` binary and the binary-free Packagist API fallback) so they
 * produce identical, dashboard-safe rows.
 */
class AdvisoryFormat
{
    public static function severity(string $severity): string
    {
        return match (strtolower(trim($severity))) {
            'critical' => 'critical',
            'high' => 'high',
            'moderate', 'medium' => 'moderate',
            'low' => 'low',
            'info', 'none' => 'info',
            default => 'unknown',
        };
    }

    /**
     * Keep an advisory link only when it is a real http(s) URL. Audit tooling
     * output is untrusted (a compromised child could craft it); a `javascript:`
     * or `data:` scheme rendered into the parent dashboard would be a stored XSS.
     */
    public static function link(mixed $link): ?string
    {
        $link = Cast::str($link);

        if ($link === '' || ! Str::startsWith(strtolower($link), ['http://', 'https://'])) {
            return null;
        }

        return $link;
    }
}
