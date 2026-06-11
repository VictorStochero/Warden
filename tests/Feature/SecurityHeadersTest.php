<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * #14 — the dashboard and login screens must carry framing/clickjacking and
 * content-type hardening headers on every response.
 */
class SecurityHeadersTest extends TestCase
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

    private function assertSecurityHeaders(TestResponse $response): void
    {
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'same-origin');

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("img-src 'self' data:", $csp);
    }

    public function test_dashboard_response_carries_security_headers(): void
    {
        Project::create(['name' => 'Demo', 'slug' => 'demo', 'token' => 't', 'secret' => 's', 'active' => true]);

        $this->assertSecurityHeaders($this->get(route('warden.overview')));
    }

    public function test_login_screen_carries_security_headers(): void
    {
        config()->set('warden.dashboard.auth.mode', 'password');
        config()->set('warden.dashboard.auth.password', 'view-secret');

        $this->assertSecurityHeaders($this->get(route('warden.login')));
    }
}
