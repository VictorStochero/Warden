<?php

namespace VictorStochero\Warden\Tests\Feature;

use VictorStochero\Warden\Tests\TestCase;

/**
 * `warden:doctor` (§5.9 DX): a one-shot diagnosis of a Warden install so an
 * operator can self-serve "why isn't anything showing up?" instead of guessing.
 */
class DoctorCommandTest extends TestCase
{
    public function test_doctor_reports_a_healthy_configured_child(): void
    {
        $this->artisan('warden:doctor')
            ->assertExitCode(0)
            ->expectsOutputToContain('child');
    }

    public function test_doctor_flags_a_disabled_kill_switch(): void
    {
        $this->app['config']->set('warden.enabled', false);

        $this->artisan('warden:doctor')
            ->expectsOutputToContain('disabled');
    }

    public function test_doctor_fails_for_an_unconfigured_child(): void
    {
        $this->app['config']->set('warden.child.parent_url', null);
        $this->app['config']->set('warden.child.token', null);

        $this->artisan('warden:doctor')
            ->assertExitCode(1)
            ->expectsOutputToContain('WARDEN_PARENT_URL');
    }
}
