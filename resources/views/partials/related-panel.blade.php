@php
    use VictorStochero\Warden\Dashboard\Format;
    use VictorStochero\Warden\Support\Cast;

    /** @var array<string, mixed> $related */
    $rTraceId = $related['trace_id'] ?? null;
    $rEntry = $related['entry'] ?? null;
    $rCounts = $related['counts'] ?? [];
    $rIssues = $related['issues'] ?? [];
    $rRecentTraces = $related['recent_traces'] ?? collect();
    $rOpenIssues = $related['open_issues'] ?? collect();
    $rIncidents = $related['incidents'] ?? collect();

    $typeColor = ['request' => 'text-brand-400', 'command' => 'text-sky-400', 'schedule' => 'text-violet-400', 'job' => 'text-emerald-400'];
@endphp

{{-- Header with collapse / expand controls. The expand button is shown only
     when the panel is collapsed (CSS-driven via the root class). --}}
<div class="flex h-16 shrink-0 items-center gap-2 border-b border-ink-700 px-4">
    <button type="button" data-wdn-related-expand aria-label="{{ __('warden::related.expand') }}" title="{{ __('warden::related.expand') }}"
        class="wdn-related-only-collapsed flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 transition hover:bg-ink-800 hover:text-white">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
    </button>
    <h2 class="wdn-related-body min-w-0 flex-1 truncate text-[13px] font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::related.heading') }}</h2>
    <button type="button" data-wdn-related-toggle aria-label="{{ __('warden::related.toggle') }}" title="{{ __('warden::related.toggle') }}"
        class="wdn-related-body flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-ink-800 hover:text-white">
        <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
    </button>
</div>

<div class="wdn-related-body flex-1 overflow-y-auto px-4 py-5 space-y-6">
    @if($rTraceId !== null)
        {{-- Contextual summary of the current trace. --}}
        <section>
            <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-slate-600">{{ __('warden::related.trace_summary') }}</p>
            @if($rEntry !== null)
                <div class="rounded-xl border border-ink-700/70 bg-ink-850 p-3">
                    <span class="text-[10px] font-semibold uppercase tracking-wider {{ $typeColor[$rEntry['type']] ?? 'text-slate-500' }}">{{ $rEntry['type'] }}</span>
                    <p class="mt-1 break-words text-[13px] text-slate-200">{{ $rEntry['label'] }}</p>
                </div>
            @endif

            @if(! empty($rCounts))
                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach($rCounts as $type => $count)
                        <span class="inline-flex items-center gap-1 rounded-md border border-ink-700/70 bg-ink-850 px-2 py-1 text-[11px] text-slate-400">
                            {{ __('warden::related.counts.'.$type) }}
                            <span class="font-mono font-medium text-slate-200">{{ Format::num(Cast::int($count)) }}</span>
                        </span>
                    @endforeach
                </div>
            @endif

            <a href="{{ route('warden.trace', ['project' => $project->slug, 'traceId' => $rTraceId]) }}"
               class="mt-3 inline-flex items-center gap-1 text-[12px] font-medium text-brand-400 transition hover:text-brand-300">
                {{ __('warden::related.view_trace') }}
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </section>

        @if(! empty($rIssues))
            <section>
                <p class="mb-2 text-[10px] font-semibold uppercase tracking-widest text-slate-600">{{ __('warden::related.related_issues') }}</p>
                <div class="rounded-xl border border-ink-700/70 bg-ink-850 divide-y divide-ink-700/70">
                    @foreach($rIssues as $issue)
                        @if(($issue['id'] ?? null) !== null)
                            <a href="{{ route('warden.issue', ['project' => $project->slug, 'issue' => $issue['id']]) }}"
                               class="flex items-center gap-2 px-3 py-2 text-[12px] transition hover:bg-ink-800/60"
                               title="{{ __('warden::related.view_issue') }}">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-rose-400"></span>
                                <span class="min-w-0 flex-1 truncate font-mono text-rose-300">{{ $issue['class'] }}</span>
                            </a>
                        @else
                            <div class="flex items-center gap-2 px-3 py-2 text-[12px] text-slate-400">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-slate-600"></span>
                                <span class="min-w-0 flex-1 truncate font-mono">{{ $issue['class'] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
    @else
        {{-- Fallback: project context (recent traces / open issues / incidents). --}}
        @php $hasAny = $rRecentTraces->isNotEmpty() || $rOpenIssues->isNotEmpty() || $rIncidents->isNotEmpty(); @endphp

        <section>
            <div class="mb-2 flex items-center justify-between">
                <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-600">{{ __('warden::related.recent_traces') }}</p>
                <a href="{{ route('warden.traces', ['project' => $project->slug]) }}" class="text-[11px] text-slate-500 transition hover:text-slate-300">{{ __('warden::related.see_all') }}</a>
            </div>
            @if($rRecentTraces->isNotEmpty())
                <div class="rounded-xl border border-ink-700/70 bg-ink-850 divide-y divide-ink-700/70">
                    @foreach($rRecentTraces as $t)
                        <a href="{{ route('warden.trace', ['project' => $project->slug, 'traceId' => $t['trace_id']]) }}"
                           class="flex items-center gap-2 px-3 py-2 text-[12px] transition hover:bg-ink-800/60">
                            <span class="w-12 shrink-0 text-[9px] font-semibold uppercase tracking-wider {{ $typeColor[$t['type']] ?? 'text-slate-500' }}">{{ $t['type'] }}</span>
                            <span class="min-w-0 flex-1 truncate {{ $t['errored'] ? 'text-rose-400' : 'text-slate-300' }}">{{ $t['label'] }}</span>
                            <span class="shrink-0 font-mono text-[10px] text-slate-500">{{ Format::dur($t['duration_us']) }}</span>
                        </a>
                    @endforeach
                </div>
            @else
                <p class="text-[12px] text-slate-600">{{ __('warden::related.empty') }}</p>
            @endif
        </section>

        @if($rOpenIssues->isNotEmpty())
            <section>
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-600">{{ __('warden::related.open_issues') }}</p>
                    <a href="{{ route('warden.issues', ['project' => $project->slug]) }}" class="text-[11px] text-slate-500 transition hover:text-slate-300">{{ __('warden::related.see_all') }}</a>
                </div>
                <div class="rounded-xl border border-ink-700/70 bg-ink-850 divide-y divide-ink-700/70">
                    @foreach($rOpenIssues as $issue)
                        <a href="{{ route('warden.issue', ['project' => $project->slug, 'issue' => $issue->id]) }}"
                           class="block px-3 py-2 text-[12px] transition hover:bg-ink-800/60">
                            <span class="block truncate font-mono text-rose-300">{{ Cast::str($issue->class ?? null, 'Exception') }}</span>
                            <span class="block truncate text-[11px] text-slate-500">{{ Cast::str($issue->message ?? null) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if($rIncidents->isNotEmpty())
            <section>
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-[10px] font-semibold uppercase tracking-widest text-slate-600">{{ __('warden::related.incidents') }}</p>
                    <a href="{{ route('warden.incidents', ['project' => $project->slug]) }}" class="text-[11px] text-slate-500 transition hover:text-slate-300">{{ __('warden::related.see_all') }}</a>
                </div>
                <div class="rounded-xl border border-ink-700/70 bg-ink-850 divide-y divide-ink-700/70">
                    @foreach($rIncidents as $incident)
                        <a href="{{ route('warden.incident', ['project' => $project->slug, 'incident' => $incident->id]) }}"
                           class="flex items-center gap-2 px-3 py-2 text-[12px] transition hover:bg-ink-800/60">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full {{ Cast::str($incident->status ?? null) === 'open' ? 'bg-rose-400' : 'bg-slate-600' }}"></span>
                            <span class="min-w-0 flex-1 truncate text-slate-300">{{ Cast::str($incident->subject ?? null) }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @unless($hasAny)
            <p class="text-[12px] text-slate-600">{{ __('warden::related.empty') }}</p>
        @endunless
    @endif
</div>
