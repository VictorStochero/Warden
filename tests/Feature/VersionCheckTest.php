<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use VictorStochero\Warden\Models\Setting;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\Updates\VersionCheck;

class VersionCheckTest extends TestCase
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

    protected function tearDown(): void
    {
        putenv('WARDEN_VERSION_CHECK');
        parent::tearDown();
    }

    private function fakePackagist(string ...$versions): void
    {
        $releases = array_map(fn (string $v) => ['version' => $v], $versions);

        Http::fake([
            '*' => Http::response(['packages' => ['victorstochero/warden' => $releases]], 200),
        ]);
    }

    public function test_run_caches_the_latest_stable_version(): void
    {
        Setting::write('version_check', ['current' => '0.3.0']); // pin a known "installed"
        $this->fakePackagist('v0.3.0', 'v0.4.0-beta.1', 'v0.4.0');

        $check = new VersionCheck;
        // current is recomputed from the installed package; force it for the test.
        $check->run(force: true);

        $latest = Setting::read('version_check')['latest'] ?? null;
        $this->assertSame('0.4.0', $latest);
    }

    public function test_notice_surfaces_when_a_newer_version_exists(): void
    {
        Setting::write('version_check', ['current' => '0.3.2', 'latest' => '0.4.0']);

        $this->assertSame(
            ['current' => '0.3.2', 'latest' => '0.4.0'],
            (new VersionCheck)->notice(),
        );
    }

    public function test_no_notice_when_up_to_date(): void
    {
        Setting::write('version_check', ['current' => '0.4.0', 'latest' => '0.4.0']);

        $this->assertNull((new VersionCheck)->notice());
    }

    public function test_dismiss_suppresses_the_notice_until_a_newer_release(): void
    {
        $check = new VersionCheck;
        Setting::write('version_check', ['current' => '0.3.2', 'latest' => '0.4.0']);

        $check->dismiss('0.4.0');
        $this->assertNull($check->notice());

        // A newer release than the dismissed one surfaces again.
        Setting::write('version_check', array_merge(
            Setting::read('version_check'),
            ['latest' => '0.5.0'],
        ));
        $this->assertSame(['current' => '0.3.2', 'latest' => '0.5.0'], $check->notice());
    }

    public function test_disabled_via_dashboard_toggle_yields_no_notice(): void
    {
        $check = new VersionCheck;
        $check->setEnabled(false);
        Setting::write('version_check', array_merge(
            Setting::read('version_check'),
            ['current' => '0.3.2', 'latest' => '0.4.0'],
        ));

        $this->assertFalse($check->enabled());
        $this->assertNull($check->notice());
    }

    public function test_env_wins_over_dashboard_toggle(): void
    {
        putenv('WARDEN_VERSION_CHECK=1');
        config()->set('warden.parent.version_check.enabled', true);

        $check = new VersionCheck;
        $check->setEnabled(false); // dashboard says off...

        $this->assertTrue($check->enabled()); // ...but .env pins it on
    }

    public function test_run_is_best_effort_on_network_failure(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);

        $check = new VersionCheck;
        $check->run(force: true);

        // No latest cached, no throw, no notice.
        $this->assertNull(Setting::read('version_check')['latest'] ?? null);
        $this->assertNull($check->notice());
    }

    public function test_dismiss_route_records_the_dismissed_version(): void
    {
        Setting::write('version_check', ['current' => '0.3.2', 'latest' => '0.4.0']);

        $this->post(route('warden.admin.version-check.dismiss'), ['version' => '0.4.0'])
            ->assertRedirect();

        $this->assertSame('0.4.0', Setting::read('version_check')['dismissed'] ?? null);
    }

    public function test_toggle_route_persists_the_setting(): void
    {
        $this->post(route('warden.admin.version-check.toggle'), ['enabled' => '0'])
            ->assertRedirect(route('warden.admin.maintenance'));

        $this->assertFalse((new VersionCheck)->enabled());
    }
}
