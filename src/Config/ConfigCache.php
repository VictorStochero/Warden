<?php

namespace VictorStochero\Warden\Config;

use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Json;

/**
 * Local, process-spanning cache of the parent-pushed sparse config, kept in
 * storage/framework/warden-config.json as {version, config}. Written by the
 * shipper when the parent pushes a new version; read at boot by RemoteConfig.
 * All file I/O is best-effort: failures fall back to "no remote config".
 */
final class ConfigCache
{
    /** @var array{version:int, config:array<string,mixed>}|null */
    private static ?array $memo = null;

    private static function path(): string
    {
        return storage_path('framework/warden-config.json');
    }

    /** @return array{version:int, config:array<string,mixed>} */
    private static function load(): array
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        $file = self::path();

        if (! is_file($file)) {
            return self::$memo = ['version' => 0, 'config' => []];
        }

        $data = Json::decode((string) @file_get_contents($file));
        $raw = isset($data['config']) && is_array($data['config']) ? $data['config'] : [];

        $config = [];
        foreach ($raw as $key => $value) {
            $config[(string) $key] = $value;
        }

        return self::$memo = ['version' => Cast::int($data['version'] ?? 0), 'config' => $config];
    }

    public static function version(): int
    {
        return self::load()['version'];
    }

    /** @return array<string, mixed> */
    public static function read(): array
    {
        return self::load()['config'];
    }

    /** @param array<string, mixed> $config */
    public static function write(int $version, array $config): void
    {
        $file = self::path();
        $dir = dirname($file);

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $payload = Json::encode(['version' => $version, 'config' => $config]);
        $tmp = $file.'.'.getmypid().'.tmp';

        if (@file_put_contents($tmp, $payload) !== false) {
            @rename($tmp, $file);
        }

        self::$memo = ['version' => $version, 'config' => $config];
    }

    public static function forget(): void
    {
        self::$memo = null;
        @unlink(self::path());
    }
}
