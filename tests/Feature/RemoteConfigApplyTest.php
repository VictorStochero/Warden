<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Config\ConfigCache;
use VictorStochero\Warden\Config\RemoteConfig;
use VictorStochero\Warden\Console\ShipCommand;
use VictorStochero\Warden\Contracts\Transport;
use VictorStochero\Warden\Tests\TestCase;

class RemoteConfigApplyTest extends TestCase
{
    protected function tearDown(): void
    {
        ConfigCache::forget();
        parent::tearDown();
    }

    public function test_config_cache_roundtrips_version_and_document(): void
    {
        ConfigCache::write(7, ['host_interval' => 30]);

        $this->assertSame(7, ConfigCache::version());
        $this->assertSame(30, ConfigCache::read()['host_interval']);
    }

    public function test_config_cache_defaults_to_zero_when_absent(): void
    {
        ConfigCache::forget();

        $this->assertSame(0, ConfigCache::version());
        $this->assertSame([], ConfigCache::read());
    }

    public function test_remote_config_applies_knob_when_env_absent(): void
    {
        ConfigCache::write(1, ['host_interval' => 42]);
        config()->set('warden.child.host_interval', 15);

        (new RemoteConfig)->apply($this->app['config']);

        $this->assertSame(42, config('warden.child.host_interval'));
    }

    public function test_remote_config_yields_to_explicit_env(): void
    {
        putenv('WARDEN_HOST_INTERVAL=9');
        ConfigCache::write(1, ['host_interval' => 42]);
        config()->set('warden.child.host_interval', 9);

        (new RemoteConfig)->apply($this->app['config']);

        $this->assertSame(9, config('warden.child.host_interval'));
        putenv('WARDEN_HOST_INTERVAL');
    }

    public function test_remote_config_applies_envless_knob_freely(): void
    {
        ConfigCache::write(1, ['sample' => ['type_gate' => ['query' => false]]]);
        config()->set('warden.child.sample.type_gate.query', true);

        (new RemoteConfig)->apply($this->app['config']);

        $this->assertFalse(config('warden.child.sample.type_gate.query'));
    }

    public function test_ship_persists_pushed_config_from_directives(): void
    {
        $transport = new class implements Transport
        {
            public function ship(array $shipments): bool
            {
                return true;
            }

            public function reportDeadLetter(string $b, string $r, int $a): bool
            {
                return true;
            }

            public function poll(): bool
            {
                return true;
            }

            public function lastDirectives(): array
            {
                return ['config_version' => 3, 'config' => ['host_interval' => 99]];
            }
        };

        (new ShipCommand)->persistPushedConfig($transport);

        $this->assertSame(3, ConfigCache::version());
        $this->assertSame(99, ConfigCache::read()['host_interval']);
    }
}
