<?php

namespace VictorStochero\Warden\Support;

/**
 * Narrows `mixed` (from config(), JSON payloads and query-builder rows) to a
 * concrete scalar with a safe fallback. Using these instead of raw `(int)` /
 * `(string)` casts keeps the code clean at PHPStan level max AND avoids the
 * runtime warnings a blind cast of an array/object would produce.
 */
class Cast
{
    public static function int(mixed $value, int $default = 0): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    public static function float(mixed $value, float $default = 0.0): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }

    public static function str(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    public static function bool(mixed $value): bool
    {
        return (bool) $value;
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function arr(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
