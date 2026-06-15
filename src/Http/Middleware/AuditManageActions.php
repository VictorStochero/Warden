<?php

namespace VictorStochero\Warden\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use VictorStochero\Warden\Audit\AuditLogger;

/**
 * Audit trail for the manage routes (§5.7). Attached to the manageWarden group,
 * it records every write (non-GET) that didn't error — so accountability is
 * structural, captured in one place instead of scattered across controllers.
 */
class AuditManageActions
{
    public function __construct(protected AuditLogger $logger) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethod('GET') && $response->getStatusCode() < 400) {
            $this->logger->record($request);
        }

        return $response;
    }
}
