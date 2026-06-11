@php
    use VictorStochero\Warden\Dashboard\Format;
    $counts = $series->pluck('count')->all();
    $errs   = $series->pluck('errors')->all();
    $p95s   = $series->map(fn ($b) => $b['p95'] ?? 0)->all();
@endphp

<div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
    {{-- Left: charts + tables --}}
    <div class="space-y-6 lg:col-span-2">
        <div class="grid gap-5 sm:grid-cols-2">
            <div class="rounded-xl border border-ink-700/70 bg-ink-850 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('warden::project.overview.throughput') }}</span>
                    <span class="text-sm font-medium text-white">{{ Format::num(array_sum($counts)) }}</span>
                </div>
                @include('warden::partials.bars', ['values' => $counts, 'color' => '#6366f1', 'height' => 60])
            </div>
            <div class="rounded-xl border border-ink-700/70 bg-ink-850 p-4">
                <div class="mb-3 flex items-center justify-between">
                    <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('warden::project.overview.p95_latency') }}</span>
                    <span class="text-sm font-medium text-amber-400">{{ $kpis['p95'] !== null ? Format::ms($kpis['p95']) : '—' }}</span>
                </div>
                @include('warden::partials.chart', ['values' => $p95s, 'color' => '#f59e0b', 'height' => 60])
            </div>
        </div>

        {{-- Top routes --}}
        @include('warden::partials.card-open', ['title' => __('warden::project.overview.top_routes'), 'action' => [__('warden::project.overview.requests_action'), route('warden.project.section', ['project' => $project->slug, 'section' => 'requests', 'range' => $range])]])
            @include('warden::partials.route-table', ['routes' => $routes])
        @include('warden::partials.card-close')

        {{-- Slow queries --}}
        @include('warden::partials.card-open', ['title' => __('warden::project.overview.slowest_queries'), 'action' => [__('warden::project.overview.queries_action'), route('warden.project.section', ['project' => $project->slug, 'section' => 'queries', 'range' => $range])]])
            @include('warden::partials.query-table', ['queries' => $slow])
        @include('warden::partials.card-close')

        {{-- Queues --}}
        @include('warden::partials.card-open', ['title' => __('warden::project.overview.queues'), 'action' => null])
            @include('warden::partials.queue-table', ['queues' => $queues])
        @include('warden::partials.card-close')
    </div>

    {{-- Right rail --}}
    <div class="space-y-6">
        @if($incidents->where('status', 'open')->count())
            <div class="rounded-xl border border-rose-500/30 bg-rose-500/5 p-4">
                <p class="mb-2 text-[11px] font-semibold uppercase tracking-wider text-rose-400">{{ __('warden::project.overview.active_incidents') }}</p>
                <div class="space-y-2">
                    @foreach($incidents->where('status', 'open') as $inc)
                        <a href="{{ route('warden.incident', [$project->slug, $inc->id]) }}"
                           class="-mx-1 flex items-start gap-2 rounded-lg px-1 py-1 text-sm transition hover:bg-rose-500/10">
                            <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full {{ $inc->severity === 'critical' ? 'bg-rose-500' : 'bg-amber-500' }}"></span>
                            <div class="min-w-0">
                                <p class="truncate text-slate-200">{{ $inc->summary ?? $inc->subject }}</p>
                                <p class="text-[11px] text-slate-500">{{ Format::ago($inc->started_at) }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif

        @include('warden::partials.card-open', ['title' => __('warden::project.overview.recent_issues'), 'action' => [__('warden::project.overview.all_action'), route('warden.issues', $project->slug)]])
            @forelse($recent_issues as $issue)
                <a href="{{ route('warden.issue', [$project->slug, $issue->id]) }}" class="flex items-start gap-3 border-t border-ink-700/70 px-4 py-3 transition first:border-0 hover:bg-ink-850/50">
                    <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-rose-500"></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm text-slate-200">{{ class_basename($issue->class) }}</p>
                        <p class="truncate text-[11px] text-slate-500">{{ $issue->message }}</p>
                    </div>
                    <span class="shrink-0 rounded-md bg-ink-700 px-1.5 py-0.5 text-[11px] font-medium text-slate-300">{{ Format::num($issue->count) }}</span>
                </a>
            @empty
                <p class="px-4 py-8 text-center text-sm text-slate-600">{{ __('warden::project.overview.no_open_issues') }}</p>
            @endforelse
        @include('warden::partials.card-close')

        @include('warden::partials.card-open', ['title' => __('warden::project.overview.heartbeats'), 'action' => null])
            @forelse($heartbeats as $hb)
                <div class="flex items-center gap-2.5 border-t border-ink-700/70 px-4 py-2.5 text-sm first:border-0">
                    <span class="h-1.5 w-1.5 rounded-full {{ $hb['healthy'] ? 'bg-emerald-500' : 'bg-rose-500' }}"></span>
                    <span class="truncate text-slate-300">{{ \Illuminate\Support\Str::after($hb['key'], 'schedule:') }}</span>
                    <span class="ml-auto text-[11px] text-slate-500">{{ Format::ago($hb['last_seen']) }}</span>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-slate-600">{{ __('warden::project.overview.no_heartbeats') }}</p>
            @endforelse
        @include('warden::partials.card-close')

        @include('warden::partials.card-open', ['title' => __('warden::project.overview.recent_traces'), 'action' => [__('warden::project.overview.all_traces_action'), route('warden.traces', $project->slug)]])
            @include('warden::partials.trace-list', ['traces' => $recent_traces, 'project' => $project])
        @include('warden::partials.card-close')
    </div>
</div>
