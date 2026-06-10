<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use VictorStochero\Warden\Support\Cast;

/**
 * Persists the viewer's dashboard language choice in the `warden_locale` cookie
 * and bounces back to where they were. GET (not POST) so the switcher is a plain
 * link and no CSRF token is needed; the cookie is a UI preference, nothing more.
 */
class LocaleController
{
    public function switch(Request $request, string $locale): RedirectResponse
    {
        $back = redirect()->to($this->safeReferer($request));

        if (! in_array($locale, $this->allowed(), true)) {
            return $back;
        }

        // One year, in minutes. Lax cookie — it carries no sensitive data.
        return $back->withCookie(cookie('warden_locale', $locale, 525600));
    }

    /**
     * Only bounce back to the referring page when it lives on this same host;
     * otherwise fall back to the overview. Stops the referer header from being
     * abused to turn the switcher into an open redirect.
     */
    protected function safeReferer(Request $request): string
    {
        $referer = Cast::str($request->headers->get('referer'));
        $host = $referer !== '' ? parse_url($referer, PHP_URL_HOST) : null;

        if (is_string($host) && $host === $request->getHost()) {
            return $referer;
        }

        return route('warden.overview');
    }

    /** @return list<string> */
    protected function allowed(): array
    {
        return array_values(array_filter(
            Cast::arr(config('warden.dashboard.locales')),
            'is_string'
        ));
    }
}
