<?php

namespace VictorStochero\Warden\Issues;

use VictorStochero\Warden\Support\Cast;

/**
 * Stable grouping key for exceptions: hash(class + normalized message + top
 * frame). Normalizing the message (stripping ids, numbers, hashes, quoted
 * literals) keeps "User 42 not found" and "User 99 not found" in one issue,
 * which is what makes storing the full stack unnecessary (§17).
 */
class Fingerprint
{
    /** @param array<array-key, mixed>|null $stack */
    public static function for(string $class, string $message, ?array $stack): string
    {
        $top = '';

        if (! empty($stack)) {
            $frame = Cast::arr($stack[0] ?? null);
            $top = Cast::str($frame['file'] ?? null).':'.Cast::str($frame['line'] ?? null).'@'.Cast::str($frame['function'] ?? null);
        }

        return hash('sha256', $class.'|'.static::normalize($message).'|'.$top);
    }

    public static function normalize(string $message): string
    {
        // Quoted literals -> placeholder.
        $message = (string) preg_replace('/([\'"]).*?\1/', '?', $message);
        // Hex / uuids / long hashes -> placeholder.
        $message = (string) preg_replace('/\b[0-9a-f]{8,}\b/i', '?', $message);
        // Bare numbers -> placeholder.
        $message = (string) preg_replace('/\b\d+\b/', '?', $message);
        // Collapse whitespace.
        $message = (string) preg_replace('/\s+/', ' ', $message);

        return trim($message);
    }
}
