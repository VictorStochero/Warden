<?php

namespace VictorStochero\Warden\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;
use VictorStochero\Warden\Support\Cast;
use VictorStochero\Warden\Support\Locales;

/**
 * Resolves the dashboard UI language for the current request and applies it via
 * App::setLocale(). Scoped to the dashboard route groups only — it never runs on
 * ingest routes nor touches the host app's own requests.
 *
 * Resolution order (first match wins):
 *   1. the `warden_locale` cookie, if it names an allowed locale;
 *   2. on a first visit (no cookie), the browser's Accept-Language matched
 *      against the allowed locales by primary subtag (pt → pt_BR, es → es);
 *   3. config('warden.dashboard.locale') (the instance default, falls back en).
 *
 * The cookie is only ever written by LocaleController when the viewer picks a
 * language in the switcher; this middleware reads, it does not persist.
 */
class SetLocale
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolve($request));

        return $next($request);
    }

    protected function resolve(Request $request): string
    {
        $allowed = $this->allowed();

        $cookie = Cast::str($request->cookie('warden_locale'));
        if ($cookie !== '' && in_array($cookie, $allowed, true)) {
            return $cookie;
        }

        $detected = $this->fromAcceptLanguage($request, $allowed);
        if ($detected !== null) {
            return $detected;
        }

        $default = Cast::str(config('warden.dashboard.locale'), 'en');

        return in_array($default, $allowed, true) ? $default : 'en';
    }

    /**
     * Match the browser's preferred languages against the allow-list by primary
     * subtag, honouring the q-weighted order the client sent.
     *
     * @param  list<string>  $allowed
     */
    protected function fromAcceptLanguage(Request $request, array $allowed): ?string
    {
        // Map primary subtag (e.g. "pt", "es") to a concrete allowed locale.
        $byPrimary = [];
        foreach ($allowed as $locale) {
            $primary = strtolower(explode('_', $locale)[0]);
            $byPrimary[$primary] ??= $locale;
        }

        foreach ($request->getLanguages() as $language) {
            $primary = strtolower(explode('_', str_replace('-', '_', $language))[0]);
            if (isset($byPrimary[$primary])) {
                return $byPrimary[$primary];
            }
        }

        return null;
    }

    /** @return list<string> */
    protected function allowed(): array
    {
        return Locales::all();
    }
}
