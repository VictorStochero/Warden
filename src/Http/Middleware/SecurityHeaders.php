<?php

namespace VictorStochero\Warden\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * #14 — sets clickjacking/framing and content-type hardening headers on every
 * dashboard (and login) response. The CSP is tailored to the self-contained
 * dashboard. Inline scripts run under a per-request nonce (§5.4/§9.5), so
 * script-src no longer needs 'unsafe-inline'; the nonce is shared to the views
 * as `wardenCspNonce` before the response renders. Inline styles still use
 * 'unsafe-inline' (style-src). `data:` covers the inlined fonts and favicon.
 * Best-effort: only sets a header when it is not already present, so a host that
 * hardens further (or sets its own CSP) always wins, and it never mutates a
 * non-standard response in a way that could break the body.
 */
class SecurityHeaders
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        // Mint the nonce before the response renders and share it to the views so
        // every inline <script> can carry it; the same value goes into the CSP.
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('warden_csp_nonce', $nonce);
        View::share('wardenCspNonce', $nonce);

        $response = $next($request);

        foreach ($this->headers($nonce) as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /** @return array<string, string> */
    private function headers(string $nonce): array
    {
        return [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'same-origin',
            'Content-Security-Policy' => "default-src 'self'; style-src 'self' 'unsafe-inline'; "
                ."script-src 'self' 'nonce-{$nonce}'; img-src 'self' data:; font-src 'self' data:; "
                ."frame-ancestors 'self'; base-uri 'self'; form-action 'self'",
        ];
    }
}
