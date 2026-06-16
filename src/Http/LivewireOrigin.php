<?php

namespace VictorStochero\Warden\Http;

use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollectionInterface;

/**
 * Resolves the *real* origin route behind a Livewire technical endpoint.
 *
 * In Livewire apps every interaction posts to `livewire/update`, so capture
 * loses which page the user was on. Given the request's `Referer`, this resolves
 * the origin page's route name against the host's RouteCollection (in memory, no
 * I/O) so the request can be relabelled to that page for Top Routes / traces.
 *
 * Best-effort by design: any failure resolves to null and capture degrades to
 * the technical endpoint — it never throws into the host (RNF-2). Privacy: only
 * the resolved route name is returned, never the raw Referer URL (which may
 * carry PII in its path/query), so no scrubbing is needed downstream.
 */
final class LivewireOrigin
{
    /** A Livewire (technical) request whose label is worth rewriting by its origin? */
    public static function isLivewire(?string $routeName, string $path): bool
    {
        return $routeName === 'livewire.update'
            || str_starts_with(ltrim($path, '/'), 'livewire/');
    }

    /**
     * Resolve the origin route name from the Referer, or null.
     * Best-effort: any error → null. Never throws.
     */
    public static function resolve(RouteCollectionInterface $routes, ?string $referer): ?string
    {
        if ($referer === null || $referer === '') {
            return null;
        }

        try {
            $path = parse_url($referer, PHP_URL_PATH);
            $path = is_string($path) ? $path : '/';

            $route = $routes->match(Request::create($path, 'GET'));
            $name = $route->getName();

            return ($name !== null && $name !== '') ? $name : '/'.ltrim($route->uri(), '/');
        } catch (\Throwable) {
            return null;
        }
    }
}
