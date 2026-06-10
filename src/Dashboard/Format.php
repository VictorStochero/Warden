<?php

namespace VictorStochero\Warden\Dashboard;

use Illuminate\Support\Carbon;

/** Tiny presentation helpers for the dashboard views (no Blade extension needed). */
class Format
{
    /** Display timezone for absolute timestamps; null = the app default. */
    protected static ?string $tz = null;

    /** Set the per-request display timezone (e.g. the current project's). */
    public static function tz(?string $timezone): void
    {
        self::$tz = ($timezone !== null && $timezone !== '') ? $timezone : null;
    }

    /** Absolute timestamp rendered in the configured display timezone. */
    public static function at(\DateTimeInterface|string|int|null $time, string $format = 'Y-m-d H:i:s'): string
    {
        if (! $time) {
            return '—';
        }

        $carbon = Carbon::parse($time);

        return (self::$tz !== null ? $carbon->setTimezone(self::$tz) : $carbon)->format($format);
    }

    /** Microseconds -> human duration. */
    public static function dur(?int $us): string
    {
        if ($us === null) {
            return '—';
        }
        if ($us < 1000) {
            return $us.'µs';
        }
        if ($us < 1_000_000) {
            return rtrim(rtrim(number_format($us / 1000, $us < 10_000 ? 1 : 0), '0'), '.').'ms';
        }

        return rtrim(rtrim(number_format($us / 1_000_000, 2), '0'), '.').'s';
    }

    /** Milliseconds -> human duration (used for already-ms values). */
    public static function ms(?int $ms): string
    {
        return $ms === null ? '—' : self::dur($ms * 1000);
    }

    public static function num(int|float|null $n): string
    {
        if ($n === null) {
            return '0';
        }
        if (abs($n) >= 1_000_000) {
            return rtrim(rtrim(number_format($n / 1_000_000, 1), '0'), '.').'M';
        }
        if (abs($n) >= 1_000) {
            return rtrim(rtrim(number_format($n / 1_000, 1), '0'), '.').'k';
        }

        return number_format((float) $n, 0);
    }

    public static function bytes(?int $bytes): string
    {
        if ($bytes === null) {
            return '—';
        }
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $v = (float) $bytes;
        while ($v >= 1024 && $i < count($units) - 1) {
            $v /= 1024;
            $i++;
        }

        return rtrim(rtrim(number_format($v, 1), '0'), '.').$units[$i];
    }

    public static function ago(\DateTimeInterface|string|int|null $time): string
    {
        if (! $time) {
            return 'never';
        }

        return Carbon::parse($time)->diffForHumans(['short' => true]);
    }

    public static function time(\DateTimeInterface|string|int|null $time): string
    {
        return self::at($time, 'H:i:s');
    }
}
