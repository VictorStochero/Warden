<?php

namespace VictorStochero\Warden\Tests\Unit;

use VictorStochero\Warden\Config\CaptureProfiles;
use VictorStochero\Warden\Config\ProjectConfig;
use VictorStochero\Warden\Tests\TestCase;

class CaptureProfilesTest extends TestCase
{
    public function test_lean_profile_gates_off_the_noisy_types_and_keeps_high_signal_ones(): void
    {
        $gate = CaptureProfiles::lean()['sample']['type_gate'];

        // Noisy / metadata types are gated off...
        foreach (['cache', 'http', 'mail', 'notification', 'user', 'command'] as $off) {
            $this->assertFalse($gate[$off] ?? null, "{$off} must be gated off in the lean profile");
        }

        // ...and the high-signal types are left untouched (absent = on by default).
        foreach (['request', 'query', 'job', 'exception', 'log', 'host', 'schedule'] as $on) {
            $this->assertArrayNotHasKey($on, $gate, "{$on} must stay on in the lean profile");
        }
    }

    public function test_lean_profile_samples_requests_and_thresholds_queries(): void
    {
        $lean = CaptureProfiles::lean();

        $this->assertSame(0.2, $lean['sample']['traces']['request']);
        $this->assertTrue($lean['sample']['always_keep']['on_exception']);
        $this->assertSame(100, $lean['query']['capture_min_ms']);
    }

    public function test_lean_profile_is_a_fixed_point_of_sanitize(): void
    {
        // The seeded profile must survive ProjectConfig::sanitize unchanged, so
        // the migrate-to-lean action and the admin form agree on its shape.
        $lean = CaptureProfiles::lean();

        $this->assertSame($lean, ProjectConfig::sanitize($lean));
    }
}
