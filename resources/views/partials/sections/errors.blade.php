@php
    use VictorStochero\Warden\Dashboard\Format;
    $errs = $series->pluck('errors')->all();
    $errorRoutes = $routes->filter(fn ($r) => ($r['errors'] ?? 0) > 0)->sortByDesc('errors')->values();
@endphp

<div class="mb-5 rounded-xl border border-ink-700 bg-ink-850 p-4 text-sm leading-relaxed text-slate-400">
    <span class="font-medium text-slate-200">Errors</span> are failed HTTP responses (status 5xx). They are distinct from
    <a href="{{ route('warden.issues', $project->slug) }}" class="text-brand-400 hover:text-brand-300">Issues</a>
    (unhandled exceptions grouped by fingerprint) and
    <a href="{{ route('warden.incidents', $project->slug) }}" class="text-brand-400 hover:text-brand-300">Incidents</a>
    (alerts opened from a down heartbeat or an open issue). A 5xx usually <em>has</em> a matching issue; a 4xx does not.
</div>

<div class="mb-5 rounded-xl border border-ink-700 bg-ink-850 p-4">
    <div class="mb-3 flex items-center justify-between">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Errors over time · 5xx</span>
        <span class="text-sm font-medium text-rose-400">{{ Format::num(array_sum($errs)) }}</span>
    </div>
    @include('warden::partials.bars', ['values' => $errs, 'color' => '#f43f5e', 'height' => 56])
</div>

@include('warden::partials.card-open', ['title' => 'Routes with errors', 'action' => null])
    @if($errorRoutes->isEmpty())
        <p class="px-4 py-10 text-center text-sm text-emerald-400">No 5xx errors in range 🎉</p>
    @else
        @include('warden::partials.route-table', ['routes' => $errorRoutes])
    @endif
@include('warden::partials.card-close')

<div class="mt-5">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'request', 'title' => 'Recent 5xx requests'])
</div>
