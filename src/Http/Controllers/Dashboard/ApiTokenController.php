<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View as ViewFactory;
use VictorStochero\Warden\Http\Controllers\Dashboard\Concerns\ResolvesContext;
use VictorStochero\Warden\Models\ApiToken;
use VictorStochero\Warden\Support\Cast;

/**
 * Manage read-only API tokens (§5.7), gated by manageWarden. The plaintext is
 * shown once on creation (flashed) and never recoverable afterwards.
 */
class ApiTokenController
{
    use ResolvesContext;

    public function index(): View
    {
        return ViewFactory::make('warden::admin.api-tokens', array_merge($this->chrome(), [
            'tokens' => ApiToken::query()->orderByDesc('id')->get(),
            'plaintext' => session('warden_new_token'),
            'showRanges' => false,
            'autoRefresh' => false,
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $name = trim(Cast::str($request->input('name')));

        if ($name === '') {
            return redirect()->route('warden.admin.api-tokens')->with('warden_error', 'A token name is required.');
        }

        [, $plaintext] = ApiToken::mint($name);

        return redirect()->route('warden.admin.api-tokens')
            ->with('warden_new_token', $plaintext)
            ->with('warden_status', 'Token created — copy it now, it will not be shown again.');
    }

    public function destroy(int $token): RedirectResponse
    {
        ApiToken::query()->whereKey($token)->delete();

        return redirect()->route('warden.admin.api-tokens')->with('warden_status', 'Token revoked.');
    }
}
