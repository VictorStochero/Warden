<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use VictorStochero\Warden\Dashboard\DashboardRepository;
use VictorStochero\Warden\Support\Cast;

trait ResolvesContext
{
    /** @var list<string> */
    protected array $ranges = ['15m', '1h', '6h', '24h', '7d', '30d'];

    /**
     * Resolve the active time range, persisting the viewer's choice across
     * sections. Precedence: a valid `?range` from the query (which the user just
     * picked) > the `warden_range` cookie (their last choice) > the `1h` default.
     * The cookie is only (re)written when the range arrives explicitly in the
     * query, so a plain page load never churns it.
     */
    protected function range(Request $request): string
    {
        $queried = Cast::str($request->query('range', ''));

        if ($queried !== '' && in_array($queried, $this->ranges, true)) {
            // One year, in minutes — same lax UI-preference cookie as the locale.
            Cookie::queue('warden_range', $queried, 525600);

            return $queried;
        }

        $cookie = Cast::str($request->cookie('warden_range'));

        return in_array($cookie, $this->ranges, true) ? $cookie : '1h';
    }

    /**
     * Whether to include the dashboard's own `warden.*` requests in the Requests
     * section. Hidden by default (a self-monitoring parent's poller dominates the
     * list); `?warden=1` opts them back in.
     */
    protected function showWarden(Request $request): bool
    {
        return Cast::bool($request->query('warden'));
    }

    /** @return array<string, mixed> shared view data for the chrome. */
    protected function chrome(): array
    {
        return [
            'ranges' => $this->ranges,
            'refresh' => Cast::int(config('warden.dashboard.refresh', 15), 15),
        ];
    }

    /**
     * View data for the "Related" side panel — a trace summary when `$traceId`
     * is given, the project-context fallback otherwise.
     *
     * @return array<string, mixed>
     */
    protected function related(DashboardRepository $repo, int $projectId, ?string $traceId = null): array
    {
        return ['related' => $repo->relatedContext($projectId, $traceId)];
    }
}
