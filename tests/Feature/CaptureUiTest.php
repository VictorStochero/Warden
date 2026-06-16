<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * The "Privacy & capture" section on the project edit page: capture.pii /
 * capture.mail_body toggles, the .env-override badge, and the credential-floor
 * notice (disable_credential_scrub is deliberately NOT exposed as a toggle).
 */
class CaptureUiTest extends TestCase
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

    public function test_edit_page_renders_privacy_section_and_capture_toggles(): void
    {
        $p = Project::create(['slug' => 'demo', 'name' => 'Demo', 'token' => 'tok-'.uniqid(), 'secret' => 'sek']);

        $this->get(route('warden.admin.projects.edit', $p))
            ->assertOk()
            ->assertSee(__('warden::project.behaviour.capture'))
            ->assertSee('config[capture][pii]')
            ->assertSee('config[capture][mail_body]')
            ->assertSee(__('warden::project.behaviour.capture_credential_floor'));
    }

    public function test_capture_toggles_reflect_current_config(): void
    {
        $p = Project::create(['slug' => 'demo', 'name' => 'Demo', 'token' => 'tok-'.uniqid(), 'secret' => 'sek',
            'config' => ['capture' => ['pii' => true, 'mail_body' => false]]]);

        $html = $this->get(route('warden.admin.projects.edit', $p))->assertOk()->getContent();

        // The pii checkbox is checked, mail_body is not.
        // Note: Blade components render as <x-warden::checkbox ... /> in the testbench HTML layer.
        preg_match('/<x-warden::checkbox\b[^\/]*config\[capture\]\[pii\][^\/]*\/>/', (string) $html, $piiMatch);
        $this->assertNotEmpty($piiMatch, 'pii checkbox tag not found in HTML');
        $this->assertStringContainsString('checked', $piiMatch[0]);

        preg_match('/<x-warden::checkbox\b[^\/]*config\[capture\]\[mail_body\][^\/]*\/>/', (string) $html, $mailMatch);
        $this->assertNotEmpty($mailMatch, 'mail_body checkbox tag not found in HTML');
        $this->assertStringNotContainsString('checked', $mailMatch[0]);
    }

    public function test_env_override_shows_badge_and_disables_pii_checkbox(): void
    {
        $p = Project::create(['slug' => 'demo', 'name' => 'Demo', 'token' => 'tok-'.uniqid(), 'secret' => 'sek']);
        $p->forceFill(['env_overrides' => ['capture.pii']])->save();

        $html = (string) $this->get(route('warden.admin.projects.edit', $p))->assertOk()->getContent();

        // The amber ".env locked" badge appears.
        $this->assertStringContainsString(__('warden::project.behaviour.capture_env_locked'), $html);

        // The pii checkbox is disabled; mail_body (not overridden) is not.
        // Note: Blade components render as <x-warden::checkbox ... /> in the testbench HTML layer.
        preg_match('/<x-warden::checkbox\b[^\/]*config\[capture\]\[pii\][^\/]*\/>/', $html, $piiMatch);
        $this->assertNotEmpty($piiMatch, 'pii checkbox tag not found in HTML');
        $this->assertStringContainsString('disabled', $piiMatch[0]);

        preg_match('/<x-warden::checkbox\b[^\/]*config\[capture\]\[mail_body\][^\/]*\/>/', $html, $mailMatch);
        $this->assertNotEmpty($mailMatch, 'mail_body checkbox tag not found in HTML');
        $this->assertStringNotContainsString('disabled', $mailMatch[0]);
    }
}
