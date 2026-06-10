<?php

namespace VictorStochero\Warden\Http\Controllers\Auth;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $input = Cast::str($request->input('password'));
        $password = $this->auth->password();
        $adminPassword = $this->auth->adminPassword();

        $isViewer = $password !== '' && hash_equals($password, $input);
        $isAdmin = $adminPassword !== '' && hash_equals($adminPassword, $input);

        // With no admin password configured, any successful login is an admin.
        if ($isViewer && $adminPassword === '') {
            $isAdmin = true;
        }

        if (! $isViewer && ! $isAdmin) {
            return redirect()->route('warden.login')
                ->with('warden_error', 'Incorrect password.');
        }

        $request->session()->regenerate();
        $request->session()->put('warden_auth', true);
        $request->session()->put('warden_auth_admin', $isAdmin);

        return redirect()->route('warden.overview');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['warden_auth', 'warden_auth_admin']);

        return redirect()->route('warden.login');
    }
}
