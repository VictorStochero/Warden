<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Config\ConfigCache;
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
}
