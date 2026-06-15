<?php

namespace VictorStochero\Warden\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Symfony\Component\HttpFoundation\Response;
use VictorStochero\Warden\Trace\Propagation;
use VictorStochero\Warden\Warden;

/**
 * Opens the trace early (so every event in the request correlates) and records
 * the request summary + flushes the buffer on terminate. The request path only
 * ever appends to memory; the flush writes to the local outbox, never the
 * network (RNF-1).
 */
class TraceRequests
{
    public function __construct(protected Warden $warden) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->warden->capturing() && ! $this->warden->hasTrace()) {
            // Fleet propagation (§29): if an upstream Warden child stamped its
            // trace on the request, continue that trace so the call chain across
            // apps stitches into a single waterfall. Absent/garbled → fresh trace.
            $inherited = Propagation::parse($request->headers->get(Propagation::HEADER));

            $this->warden->startTrace('request', $inherited, name: $request->path());
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->warden->capturing() || ! $this->warden->hasTrace()) {
            return;
        }

        if ($this->warden->recorderEnabled('request')) {
            $trace = $this->warden->trace();
            $route = $request->route();
            $routeName = $route instanceof Route
                ? ($route->getName() ?: '/'.ltrim($route->uri(), '/'))
                : null;

            $this->warden->record('request', [
                'method' => $request->getMethod(),
                'route' => $routeName,
                'path' => '/'.ltrim($request->path(), '/'),
                'status' => $response->getStatusCode(),
                'memory' => memory_get_peak_usage(true),
                'user_id' => $this->warden->userId(),
            ], durationUs: $trace?->root->elapsedUs());
        }

        $this->warden->flush();
    }
}
