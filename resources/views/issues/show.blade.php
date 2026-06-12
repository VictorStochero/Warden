@extends('warden::layout', ['active' => 'issues', 'showRanges' => false])
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', class_basename($issue->class))
@section('heading', class_basename($issue->class))

@section('content')
    <div class="mb-5">
        <a href="{{ route('warden.issues', $project->slug) }}" class="text-[13px] text-brand-400 hover:text-brand-300">{{ __('warden::issues.show.back') }}</a>
    </div>

    <div class="rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10 p-5">
        <div class="flex items-start gap-3">
            <span class="mt-1.5 h-2.5 w-2.5 rounded-full {{ $issue->status === 'open' ? 'bg-rose-500' : 'bg-slate-600' }}"></span>
            <div class="min-w-0">
                <h2 class="font-mono text-[15px] text-white">{{ $issue->class }}</h2>
                <p class="mt-1 text-sm text-slate-400">{{ $issue->message }}</p>
            </div>
            <span class="ml-auto shrink-0 rounded-lg px-2 py-1 text-xs font-medium uppercase tracking-wider
                {{ $issue->status === 'open' ? 'bg-rose-500/10 text-rose-400' : 'bg-ink-700 text-slate-400' }}">{{ $issue->status }}</span>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div><p class="text-[11px] uppercase tracking-wider text-slate-500">{{ __('warden::issues.show.stat_events') }}</p><p class="mt-0.5 text-lg font-semibold text-white">{{ Format::num($issue->count) }}</p></div>
            <div><p class="text-[11px] uppercase tracking-wider text-slate-500">{{ __('warden::issues.show.stat_users_affected') }}</p><p class="mt-0.5 text-lg font-semibold text-white">{{ Format::num($issue->users_affected) }}</p></div>
            <div><p class="text-[11px] uppercase tracking-wider text-slate-500">{{ __('warden::issues.show.stat_first_seen') }}</p><p class="mt-0.5 text-sm text-slate-300">{{ Format::ago($issue->first_seen_at) }}</p></div>
            <div><p class="text-[11px] uppercase tracking-wider text-slate-500">{{ __('warden::issues.show.stat_last_seen') }}</p><p class="mt-0.5 text-sm text-slate-300">{{ Format::ago($issue->last_seen_at) }}</p></div>
        </div>

        @if($issue->last_trace_id)
            <div class="mt-4 border-t border-ink-700/70 pt-4">
                <a href="{{ route('warden.trace', [$project->slug, $issue->last_trace_id]) }}"
                   class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-400 hover:text-brand-300">
                    {{ __('warden::issues.show.view_last_trace') }}
                    <span class="font-mono text-[11px] text-slate-500">{{ \Illuminate\Support\Str::limit($issue->last_trace_id, 12, '') }}</span> →
                </a>
            </div>
        @endif
    </div>

    @php $occurrences = $occurrences ?? collect(); @endphp
    @if($occurrences->isNotEmpty())
        <div class="mt-5 overflow-hidden rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10">
            <div class="border-b border-ink-700/70 px-5 py-3.5">
                <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::issues.show.occurrences') }}</h3>
                <p class="mt-1 text-xs text-slate-500">{{ __('warden::issues.show.occurrences_hint') }}</p>
            </div>
            <div class="divide-y divide-ink-700/70">
                @foreach($occurrences as $o)
                    @php $op = is_array($o->payload) ? $o->payload : []; @endphp
                    <div class="flex items-center gap-3 px-4 py-2.5 text-sm transition hover:bg-ink-850/50">
                        <a href="{{ route('warden.event', [$project->slug, $o->id]) }}" class="flex min-w-0 flex-1 items-center gap-3">
                            <span class="w-24 shrink-0 text-[11px] text-slate-500">{{ Format::ago($o->occurred_at) }}</span>
                            <span class="min-w-0 flex-1">
                                @if(! empty($op['method']) || ! empty($op['route']) || ! empty($op['path']))
                                    <span class="rounded-md bg-ink-850 px-1.5 py-0.5 text-[10px] text-slate-400 ring-1 ring-inset ring-ink-700/50">{{ $op['method'] ?? '' }} {{ $op['route'] ?? $op['path'] ?? '' }}</span>
                                @endif
                                <span class="truncate text-[12px] text-slate-400">{{ \Illuminate\Support\Str::limit((string) ($op['message'] ?? ''), 110) }}</span>
                            </span>
                            @if(($op['user_id'] ?? null) !== null)
                                <span class="shrink-0 text-[11px] text-slate-500">{{ __('warden::issues.show.occ_user') }} {{ $op['user_id'] }}</span>
                            @endif
                        </a>
                        @if($o->trace_id)
                            <a href="{{ route('warden.trace', [$project->slug, $o->trace_id]) }}"
                               class="shrink-0 rounded bg-brand-500/10 px-1.5 py-0.5 font-mono text-[10px] font-medium text-brand-400 ring-1 ring-inset ring-brand-500/20 transition hover:bg-brand-500/20"
                               title="{{ $o->trace_id }}">{{ __('warden::issues.show.occ_trace') }} →</a>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if(!empty($issue->stack))
        <div class="mt-5 overflow-hidden rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10">
            <div class="border-b border-ink-700/70 px-5 py-3.5"><h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('warden::issues.show.stack_trace') }}</h3></div>
            <div class="divide-y divide-ink-700/60 font-mono text-[12px]">
                @foreach($issue->stack as $i => $frame)
                    <div class="flex gap-3 px-4 py-2 {{ $i === 0 ? 'bg-rose-500/5' : '' }}">
                        <span class="w-6 shrink-0 text-right text-slate-600">{{ $i }}</span>
                        <div class="min-w-0">
                            @if(!empty($frame['class']) || !empty($frame['function']))
                                <p class="text-slate-200">{{ $frame['class'] ?? '' }}{{ !empty($frame['class']) ? '::' : '' }}{{ $frame['function'] ?? '' }}()</p>
                            @endif
                            <p class="truncate text-slate-500">{{ $frame['file'] ?? '?' }}<span class="text-slate-600">:{{ $frame['line'] ?? '?' }}</span></p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@endsection
