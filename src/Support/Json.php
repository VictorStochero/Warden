<?php

namespace VictorStochero\Warden\Support;

/**
 * JSON helpers that keep static analysis honest: decode() always returns an
 * array (never mixed), so callers can index into it without tripping max-level
 * offset/iterable checks. Values inside are still mixed — narrow them with Cast.
 */
class Json
{
    /**
     * @return array<string, mixed>
     */
    public static function decode(mixed $json): array
    {
        if (! is_string($json) || $json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $key => $value) {
            $out[(string) $key] = $value;
        }

        return $out;
    }

    public static function encode(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $json === false ? '[]' : $json;
    }
}
