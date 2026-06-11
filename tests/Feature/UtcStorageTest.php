<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Warden;

class UtcStorageTest extends TestCase
{
    public function test_micro_now_is_utc_even_when_app_timezone_offset(): void
    {
        config()->set('app.timezone', 'America/Sao_Paulo'); // -3
        $warden = $this->app->make(Warden::class);

        // microNow é protected; exercite via reflexão ou um método público que o use.
        $ref = new \ReflectionMethod($warden, 'microNow');
        $ref->setAccessible(true);
        $micro = $ref->invoke($warden);                 // string 'Y-m-d H:i:s.u'
        $expectedUtc = now()->utc()->format('Y-m-d H'); // mesma hora UTC

        $this->assertSame($expectedUtc, substr($micro, 0, 13));
    }
}
