<?php

namespace VictorStochero\Warden\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * #14 — sets clickjacking/framing and content-type hardening headers on every
 * dashboard (and login) response. The CSP is tailored to the self-contained
 * dashboard, which uses inline styles/scripts and `data:` URIs for the inlined
 * fonts and favicon — hence 'unsafe-inline' for style/script and `data:` for
 * img/font. Best-effort: only sets a header when it is not already present, so
 * a host that hardens further (or sets its own CSP) always wins, and it never
 * mutates a non-standard response in a way that could break the body.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'same-origin',
        'Content-Security-Policy' => "default-src 'self'; style-src 'self' 'unsafe-inline'; "
            ."script-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; "
            ."frame-ancestors 'self'; base-uri 'self'; form-action 'self'",
    ];

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
