@php
    use VictorStochero\Warden\Dashboard\Format;
    $counts = $series->pluck('count')->all();
    $p95s   = $series->map(fn ($b) => $b['p95'] ?? 0)->all();
    $errs   = $series->pluck('errors')->all();
    $showWarden = $showWarden ?? false;
    $panelToggle = $showWarden
        ? [__('warden::project.requests.hide_panel'), request()->fullUrlWithQuery(['warden' => null])]
        : [__('warden::project.requests.show_panel'), request()->fullUrlWithQuery(['warden' => 1])];
@endphp

@if(!empty($deploys) && $deploys->isNotEmpty())
    <div class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-ink-700/70 bg-ink-850 px-4 py-2.5 text-[12px]">
        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('warden::project.requests.deploys') }}</span>
        @foreach($deploys as $d)
            <span class="flex items-center gap-1.5">
                <span class="h-1.5 w-1.5 rounded-full bg-brand-400"></span>
                <span class="font-mono text-brand-300">{{ $d->release }}</span>
                <span class="text-slate-500">{{ Format::ago($d->first_seen) }}</span>
            </span>
        @endforeach
    </div>
@endif

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
    @include('warden::partials.card-open', ['title' => __('warden::project.requests.routes_title'), 'action' => $panelToggle])
        @include('warden::partials.route-table', ['routes' => $routes, 'project' => $project])
    @include('warden::partials.card-close')
</div>

<div class="mt-6">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'request', 'title' => __('warden::project.requests.recent_title')])
</div>
