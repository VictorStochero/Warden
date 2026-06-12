<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Http;
use VictorStochero\Warden\Tests\TestCase;

class DoctorCommandTest extends TestCase
{
    public function test_a_configured_child_with_a_reachable_parent_passes(): void
    {
        Http::fake(['*' => Http::response(['accepted' => 0, 'config_version' => 0], 202)]);

        $this->artisan('warden:doctor')->assertSuccessful();
    }

    public function test_a_child_without_credentials_fails(): void
    {
        config()->set('warden.child.parent_url', '');
        config()->set('warden.child.token', '');
        config()->set('warden.child.secret', '');

        $this->artisan('warden:doctor')
            ->expectsOutputToContain('WARDEN_PARENT_URL')
            ->assertFailed();
    }

    public function test_a_child_with_an_unreachable_parent_fails(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->artisan('warden:doctor')->assertFailed();
    }

    public function test_a_migrated_parent_passes(): void
    {
        config()->set('warden.mode', 'parent');

        $this->artisan('warden:doctor')->assertSuccessful();
    }
}
