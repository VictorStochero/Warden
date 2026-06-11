<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Dashboard\Format;
use VictorStochero\Warden\Tests\TestCase;

class TimezoneDisplayTest extends TestCase
{
    protected function tearDown(): void
    {
        Format::tz(null);
        parent::tearDown();
    }

    public function test_absolute_time_is_rendered_in_app_timezone_from_utc_storage(): void
    {
        config()->set('app.timezone', 'America/Sao_Paulo'); // -3
        Format::tz(null); // sem override per-projeto → usa o fuso do app

        // Instante armazenado em UTC: 20:00 UTC == 17:00 em Sao_Paulo
        $this->assertSame('2026-06-10 17:00:00', Format::at('2026-06-10 20:00:00'));
    }

    public function test_ago_is_correct_regardless_of_app_timezone(): void
    {
        config()->set('app.timezone', 'America/Sao_Paulo');

        // Um instante 1h atrás em UTC
        $utc = now()->utc()->subHour()->format('Y-m-d H:i:s');
        $this->assertStringContainsString('1', Format::ago($utc)); // "1h" / "1 hora" etc — não "4h"
    }
}
