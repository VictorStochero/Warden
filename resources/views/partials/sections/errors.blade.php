@php
    use VictorStochero\Warden\Dashboard\Format;
    $errs = $series->pluck('errors')->all();
    $errorRoutes = $routes->filter(fn ($r) => ($r['errors'] ?? 0) > 0)->sortByDesc('errors')->values();
@endphp

<div class="mb-6 rounded-xl border border-ink-700/70 bg-ink-850 p-4 text-sm leading-relaxed text-slate-400">
    {!! __('warden::project.errors.definition_html', ['issues_url' => route('warden.issues', $project->slug), 'incidents_url' => route('warden.incidents', $project->slug)]) !!}
</div>

<div class="mb-6 rounded-xl border border-ink-700/70 bg-ink-850 p-4">
    <div class="mb-3 flex items-center justify-between">
        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('warden::project.errors.chart_label') }}</span>
        <span class="text-sm font-medium text-rose-400">{{ Format::num(array_sum($errs)) }}</span>
    </div>
    @include('warden::partials.bars', ['values' => $errs, 'color' => '#f43f5e', 'height' => 56])
</div>

@include('warden::partials.card-open', ['title' => __('warden::project.errors.routes_title'), 'action' => null])
    @if($errorRoutes->isEmpty())
        <p class="px-4 py-10 text-center text-sm text-emerald-400">{{ __('warden::project.errors.routes_empty') }}</p>
    @else
        @include('warden::partials.route-table', ['routes' => $errorRoutes])
    @endif
@include('warden::partials.card-close')

@if(isset($exceptions) && ! $exceptions->isEmpty())
    <div class="mt-6">
        @include('warden::partials.event-list', ['events' => $exceptions, 'type' => 'exception', 'title' => __('warden::project.errors.exceptions_title')])
    </div>
@endif

<div class="mt-6">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'request', 'title' => __('warden::project.errors.recent_title')])
</div>
