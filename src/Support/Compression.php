<?php

namespace VictorStochero\Warden\Support;

/**
 * Optional gzip for the child→parent channel. The HMAC is always computed over
 * the *uncompressed* JSON, so the parent inflates before verifying — signature
 * semantics are unchanged and an old (uncompressed) child keeps working.
 */
final class Compression
{
    /** gzip-encode, or null if the extension/encode is unavailable. */
    public static function deflate(string $data): ?string
    {
        if (! function_exists('gzencode')) {
            return null;
        }

        $out = gzencode($data, 6);

        return $out === false ? null : $out;
    }

    /**
     * gzip-decode, capped at $maxBytes of output so a compression bomb can't
     * exhaust memory. Returns null when the input is not valid gzip. A truncated
     * (over-cap) result simply fails the subsequent HMAC check.
     */
    public static function inflate(string $data, int $maxBytes): ?string
    {
        if (! function_exists('gzdecode')) {
            return null;
        }

        $out = @gzdecode($data, $maxBytes);

        return $out === false ? null : $out;
    }
}
