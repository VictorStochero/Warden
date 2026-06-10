@php
    use VictorStochero\Warden\Dashboard\Format;
    $counts = $series->pluck('count')->all();
    $p95s   = $series->map(fn ($b) => $b['p95'] ?? 0)->all();
    $errs   = $series->pluck('errors')->all();
@endphp

<div class="grid gap-5 sm:grid-cols-3">
    <div class="rounded-xl border border-ink-700/70 bg-ink-850 p-4">
        <p class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('warden::project.requests.throughput') }}</p>
        @include('warden::partials.bars', ['values' => $counts, 'color' => '#6366f1', 'height' => 64])
    </div>
    <div class="rounded-xl border border-ink-700/70 bg-ink-850 p-4">
        <p class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('warden::project.requests.errors') }}</p>
        @include('warden::partials.bars', ['values' => $errs, 'color' => '#f43f5e', 'height' => 64])
    </div>
    <div class="rounded-xl border border-ink-700/70 bg-ink-850 p-4">
        <p class="mb-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('warden::project.requests.p95_latency') }}</p>
        @include('warden::partials.chart', ['values' => $p95s, 'color' => '#f59e0b', 'height' => 64])
    </div>
</div>

<div class="mt-6">
    @include('warden::partials.card-open', ['title' => __('warden::project.requests.routes_title'), 'action' => null])
        @include('warden::partials.route-table', ['routes' => $routes])
    @include('warden::partials.card-close')
</div>

<div class="mt-6">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'request', 'title' => __('warden::project.requests.recent_title')])
</div>
