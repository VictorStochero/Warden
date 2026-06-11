<?php

namespace VictorStochero\Warden\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Log;
use VictorStochero\Warden\Tests\TestCase;
use VictorStochero\Warden\WardenServiceProvider;

/**
 * #15 — in password auth mode the dashboard relies on session + CSRF protection
 * coming from the configured middleware stack (default `web`). If an operator
 * strips that stack, the provider must log a best-effort warning so the missing
 * CSRF/session protection is visible. It must never throw.
 */
class CsrfMiddlewareWarningTest extends TestCase
{
    protected function observerMode(): string
    {
        return 'parent';
    }

    private function invokeWarn(): void
    {
        $provider = new WardenServiceProvider($this->app);
        $method = new \ReflectionMethod($provider, 'warnIfCsrfDisabled');
        $method->invoke($provider);
    }

    public function test_warns_when_password_mode_has_no_session_middleware(): void
    {
        config()->set('warden.dashboard.auth.mode', 'password');
        config()->set('warden.dashboard.auth.password', 'secret');
        config()->set('warden.dashboard.middleware', []); // stripped 'web'

        Log::shouldReceive('warning')->once();

        $this->invokeWarn();
    }

    public function test_does_not_warn_when_web_is_present(): void
    {
        config()->set('warden.dashboard.auth.mode', 'password');
        config()->set('warden.dashboard.auth.password', 'secret');
        config()->set('warden.dashboard.middleware', ['web']);

        Log::shouldReceive('warning')->never();

        $this->invokeWarn();
    }

    public function test_does_not_warn_when_session_and_csrf_are_explicit(): void
    {
        config()->set('warden.dashboard.auth.mode', 'password');
        config()->set('warden.dashboard.auth.password', 'secret');
        config()->set('warden.dashboard.middleware', [
            StartSession::class,
            VerifyCsrfToken::class,
        ]);

        Log::shouldReceive('warning')->never();

        $this->invokeWarn();
    }

    public function test_does_not_warn_outside_password_mode(): void
    {
        config()->set('warden.dashboard.auth.mode', 'email');
        config()->set('warden.dashboard.middleware', []);

        Log::shouldReceive('warning')->never();

        $this->invokeWarn();
    }
}
