<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Support\Version;
use VictorStochero\Warden\Tests\TestCase;

class VersionFooterTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    protected function setUp(): void
    {
        parent::setUp();
        Gate::define('viewWarden', fn ($u = null) => true);
        Gate::define('manageWarden', fn ($u = null) => true);
    }

    public function test_dashboard_footer_shows_the_package_version(): void
    {
        $this->get(route('warden.overview'))
            ->assertOk()
            ->assertSee((string) Version::current());
    }
}
