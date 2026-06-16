<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use VictorStochero\Warden\Models\Project;
use VictorStochero\Warden\Tests\TestCase;

/**
 * CSP hardening (§5.4 / §9.5): the dashboard's inline scripts run under a
 * per-request nonce, so script-src no longer needs 'unsafe-inline'. The nonce in
 * the header matches the one on the rendered <script> tags.
 */
class CspNonceTest extends TestCase
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
        Project::firstOrCreate(['slug' => 'demo'], ['name' => 'Demo', 'token' => 't', 'secret' => 's', 'active' => true]);
    }

    public function test_script_src_uses_a_nonce_and_drops_unsafe_inline(): void
    {
        $response = $this->get(route('warden.overview'));
        $response->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');

        $this->assertSame(1, preg_match('/script-src ([^;]+)/', $csp, $m));
        $scriptSrc = $m[1];

        $this->assertStringContainsString("'nonce-", $scriptSrc);
        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
    }

    public function test_inline_scripts_carry_the_header_nonce(): void
    {
        $response = $this->get(route('warden.overview'));
        $response->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertSame(1, preg_match("/'nonce-([^']+)'/", $csp, $m));
        $nonce = $m[1];

        // Every inline <script> on the page must carry the same nonce.
        $html = $response->getContent();
        $this->assertStringContainsString('<script nonce="'.$nonce.'"', $html);
    }
}
