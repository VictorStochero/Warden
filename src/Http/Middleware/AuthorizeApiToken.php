<?php

namespace VictorStochero\Warden\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use VictorStochero\Warden\Models\ApiToken;
use VictorStochero\Warden\Support\Cast;

/**
 * Authenticates the read-only API (§5.7) with a bearer token checked against the
 * SHA-256 hashes in wdn_api_tokens. A missing or unknown token gets a flat 401;
 * a valid one has its last_used_at stamped and passes through.
 */
class AuthorizeApiToken
{
    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $token = ApiToken::findByPlaintext(Cast::str($request->bearerToken()));

        if ($token === null) {
            return response()->json(['message' => 'Invalid API token.'], 401);
        }

        $token->forceFill(['last_used_at' => now()])->save();

        return $next($request);
    }
}
