<?php

namespace VictorStochero\Warden\Support;

/**
 * Single source of truth for the dashboard's offered locales (middleware, locale
 * route, language switcher). Falls back to the package defaults when the config key
 * is missing or empty — a stale published config/warden.php whose `dashboard` block
 * predates the `locales` key would otherwise empty the switcher, because Laravel's
 * mergeConfigFrom is shallow and replaces the whole block.
 */
final class Locales
{
    /** @var list<string> */
    private const DEFAULTS = ['en', 'pt_BR', 'es'];

    /** @return list<string> */
    public static function all(): array
    {
        $locales = array_values(array_filter(
            Cast::arr(config('warden.dashboard.locales')),
            static fn ($locale): bool => is_string($locale) && $locale !== '',
        ));

        return $locales === [] ? self::DEFAULTS : $locales;
    }
}
