@php
    use VictorStochero\Warden\Dashboard\Format;
    $counts = $series->pluck('count')->all();
    $p95s   = $series->map(fn ($b) => $b['p95'] ?? 0)->all();
    $errs   = $series->pluck('errors')->all();
@endphp

<div class="grid gap-4 sm:grid-cols-3">
    <div class="rounded-xl border border-ink-700 bg-ink-850 p-4">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Throughput</p>
        @include('warden::partials.bars', ['values' => $counts, 'color' => '#6366f1', 'height' => 64])
    </div>
    <div class="rounded-xl border border-ink-700 bg-ink-850 p-4">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Errors</p>
        @include('warden::partials.bars', ['values' => $errs, 'color' => '#f43f5e', 'height' => 64])
    </div>
    <div class="rounded-xl border border-ink-700 bg-ink-850 p-4">
        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">p95 latency</p>
        @include('warden::partials.chart', ['values' => $p95s, 'color' => '#f59e0b', 'height' => 64])
    </div>
</div>

<div class="mt-5">
    @include('warden::partials.card-open', ['title' => 'Routes', 'action' => null])
        @include('warden::partials.route-table', ['routes' => $routes])
    @include('warden::partials.card-close')
</div>

<div class="mt-5">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'request', 'title' => 'Recent requests'])
</div>
