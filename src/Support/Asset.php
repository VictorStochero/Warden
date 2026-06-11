<?php

namespace VictorStochero\Warden\Support;

/**
 * Resolves the dashboard's static assets (stylesheet, fonts, favicon) straight from
 * the package's resources/dist directory. There is no vendor:publish step: the CSS
 * is served by AssetController, so it can never go stale against the markup and the
 * host needs no writable public/ directory.
 *
 * The stylesheet is assembled once with its @font-face sources embedded as data:
 * URIs — a single request carries the CSS and all nine fonts — and the result is
 * memoised for the process lifetime (each release ships an immutable asset).
 */
final class Asset
{
    private static ?string $css = null;

    private static ?string $version = null;

    private static ?string $favicon = null;

    /** The dashboard stylesheet with every font reference inlined as a data: URI. */
    public static function css(): string
    {
        if (self::$css !== null) {
            return self::$css;
        }

        $css = (string) file_get_contents(self::dist('warden.css'));

        $inlined = preg_replace_callback(
            "#url\\(['\"]?fonts/([a-z0-9-]+\\.woff2)['\"]?\\)#i",
            static function (array $match): string {
                $path = self::dist('fonts/'.$match[1]);

                if (! is_file($path)) {
                    return $match[0];
                }

                return 'url(data:font/woff2;base64,'.base64_encode((string) file_get_contents($path)).')';
            },
            $css
        );

        return self::$css = (string) $inlined;
    }

    /** Short content hash of the stylesheet — drives cache-busting and the ETag. */
    public static function version(): string
    {
        if (self::$version !== null) {
            return self::$version;
        }

        $file = self::dist('warden.css');
        $hash = is_file($file) ? (string) hash_file('xxh128', $file) : 'dev';

        return self::$version = substr($hash, 0, 12);
    }

    /** Base64 of the brand mark, for an inline favicon data: URI. */
    public static function favicon(): string
    {
        if (self::$favicon !== null) {
            return self::$favicon;
        }

        $file = self::dist('warden-mark.svg');

        return self::$favicon = base64_encode(is_file($file) ? (string) file_get_contents($file) : '');
    }

    private static function dist(string $path): string
    {
        return __DIR__.'/../../resources/dist/'.$path;
    }
}
