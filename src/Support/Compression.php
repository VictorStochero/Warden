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
     * exhaust memory. Returns null when the input is not valid gzip or when the
     * decompressed payload exceeds the cap — an oversized body is rejected as
     * 413 outright instead of being truncated and wasting an HMAC pass that can
     * only fail.
     */
    public static function inflate(string $data, int $maxBytes): ?string
    {
        if (! function_exists('gzdecode')) {
            return null;
        }

        $out = @gzdecode($data, $maxBytes + 1);

        if ($out === false || strlen($out) > $maxBytes) {
            return null;
        }

        return $out;
    }
}
