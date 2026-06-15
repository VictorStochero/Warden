<?php

namespace VictorStochero\Warden\Http\Controllers\Dashboard\Concerns;

use Illuminate\Http\Request;
use VictorStochero\Warden\Support\Cast;

trait ResolvesContext
{
    /** @var list<string> */
    protected array $ranges = ['15m', '1h', '6h', '24h', '7d'];

    protected function range(Request $request): string
    {
        $range = Cast::str($request->query('range', '1h'), '1h');

        return in_array($range, $this->ranges, true) ? $range : '1h';
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
}
