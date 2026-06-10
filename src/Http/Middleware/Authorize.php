<?php

namespace VictorStochero\Warden\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use VictorStochero\Warden\Dashboard\DashboardAuth;

/**
 * Guards a dashboard route with a Gate ability, passed as the middleware
 * parameter (defaults to "viewWarden"; write routes use "manageWarden").
 * By default both abilities are granted per the selected access mode (see
 * config warden.dashboard.auth); host apps still override with their own
 * Gate::define(...). In "password" mode a denied request is redirected to the
 * built-in login form instead of a flat 403.
 * Note: the ability names viewWarden/manageWarden are a public contract
 * with the host app and must not be renamed.
 */
class Authorize
{
    public function __construct(protected DashboardAuth $auth) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next, string $ability = 'viewWarden'): Response
    {
        if (Gate::allows($ability, [$request->user()])) {
            return $next($request);
        }

        // Password mode: a logged-out viewer is sent to the login form so they
        // can authenticate, rather than hitting a dead-end 403.
        if ($this->auth->isPasswordMode() && ! $request->session()->get('warden_auth')) {
            return redirect()->guest(route('warden.login'));
        }

        abort(403, 'Warden dashboard access denied.');
    }
}
