<?php

namespace VictorStochero\Warden\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Dashboard\DashboardAuth;
use VictorStochero\Warden\Support\Cast;

/**
 * Built-in login for the "password" dashboard auth mode. It is intentionally
 * tiny and self-contained: a single shared password (and an optional admin
 * password) compared timing-safe, with the result kept in the session. It does
 * not touch the host app's user system, which makes it ideal for a dedicated
 * parent deployment. Inert in any other auth mode.
 */
class DashboardLoginController
{
    public function __construct(protected DashboardAuth $auth) {}

    public function showLogin(): View|RedirectResponse
    {
        if (! $this->auth->isPasswordMode()) {
            return redirect()->route('warden.overview');
        }

        return ViewFactory::make('warden::auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        if (! $this->auth->isPasswordMode()) {
            return redirect()->route('warden.overview');
        }

        $key = $this->throttleKey($request);
        $globalKey = $this->globalThrottleKey();
        $maxAttempts = Cast::int($this->config('max_attempts'), 5);
        $globalMax = Cast::int($this->globalMax(), 100);

        // Two-axis brute-force guard:
        //  - per-IP: blocks a single source after `max_attempts` misses;
        //  - global: an absolute cap across ALL IPs in the same window, so a
        //    distributed pool can't multiply the per-IP budget. Either tripping
        //    locks the form. A successful login clears both counters.
        $tooMany = ($maxAttempts > 0 && RateLimiter::tooManyAttempts($key, $maxAttempts))
            || ($globalMax > 0 && RateLimiter::tooManyAttempts($globalKey, $globalMax));

        if ($tooMany) {
            $seconds = max(RateLimiter::availableIn($key), RateLimiter::availableIn($globalKey));

            return redirect()->route('warden.login')->with(
                'warden_error',
                Cast::str(__('warden::auth.throttled', ['seconds' => $seconds]))
            );
        }

        $input = Cast::str($request->input('password'));
        $password = $this->auth->password();
        $adminPassword = $this->auth->adminPassword();

        // Fail-closed: management is granted ONLY when an admin password is
        // configured and the submitted value matches it. With no admin password
        // set, a successful login is viewer-only — never promoted to admin by
        // the mere absence of an admin credential.
        $isAdmin = $adminPassword !== '' && hash_equals($adminPassword, $input);
        $matchedViewer = $password !== '' && hash_equals($password, $input);
        $authenticated = $matchedViewer || $isAdmin;

        if (! $authenticated) {
            $decay = Cast::int($this->config('decay'), 60);

            if ($maxAttempts > 0) {
                RateLimiter::hit($key, $decay);
            }

            if ($globalMax > 0) {
                $globalHits = RateLimiter::hit($globalKey, $decay);

                // Best-effort signal once the aggregate cap is reached (likely a
                // distributed brute-force, invisible to the per-IP counter).
                if ($globalHits >= $globalMax) {
                    $this->reportGlobalLockout($request, $globalHits);
                }
            }

            return redirect()->route('warden.login')
                ->with('warden_error', Cast::str(__('warden::auth.incorrect')));
        }

        // Clear only this IP's counter on success. The global aggregate counter
        // is left to decay on its own window — a single valid login must not
        // reset the cap that protects against a distributed attack.
        RateLimiter::clear($key);

        $request->session()->regenerate();
        $request->session()->put('warden_auth', true);
        $request->session()->put('warden_auth_admin', $isAdmin);

        return redirect()->route('warden.overview');
    }

    protected function throttleKey(Request $request): string
    {
        return 'warden-login|'.Cast::str($request->ip(), 'unknown');
    }

    /**
     * The aggregate, IP-independent throttle key. A single bucket for every
     * login attempt enforces an absolute cap regardless of how many source IPs
     * a distributed attacker rotates through.
     */
    protected function globalThrottleKey(): string
    {
        return 'warden-login-global';
    }

    /** The absolute attempt cap across all IPs within the decay window. */
    protected function globalMax(): mixed
    {
        return config('warden.dashboard.auth.login_global_max');
    }

    /** Best-effort warning when the global login cap trips (RNF-2: never throws). */
    protected function reportGlobalLockout(Request $request, int $hits): void
    {
        try {
            Log::warning('Warden: dashboard login global throttle tripped (possible distributed brute-force).', [
                'hits' => $hits,
                'ip' => Cast::str($request->ip(), 'unknown'),
            ]);
        } catch (\Throwable) {
            // Logging must never break the login flow.
        }
    }

    protected function config(string $key): mixed
    {
        return config("warden.dashboard.auth.throttle.{$key}");
    }

    public function logout(Request $request): RedirectResponse
    {
        // Fully tear down the authenticated session: drop our flags, rotate the
        // session id (defeats fixation) and reissue the CSRF token so no stale
        // credential survives the logout.
        $request->session()->forget(['warden_auth', 'warden_auth_admin']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('warden.login');
    }
}
