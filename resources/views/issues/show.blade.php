@extends('warden::layout', ['active' => 'issues', 'showRanges' => false])
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', class_basename($issue->class))
@section('heading', class_basename($issue->class))

@section('content')
    <div class="mb-5">
        <a href="{{ route('warden.issues', $project->slug) }}" class="text-[13px] text-brand-400 hover:text-brand-300">← All issues</a>
    </div>

    <div class="rounded-xl border border-ink-700 bg-ink-850 p-5">
        <div class="flex items-start gap-3">
            <span class="mt-1.5 h-2.5 w-2.5 rounded-full {{ $issue->status === 'open' ? 'bg-rose-500' : 'bg-slate-600' }}"></span>
            <div class="min-w-0">
                <h2 class="font-mono text-[15px] text-white">{{ $issue->class }}</h2>
                <p class="mt-1 text-sm text-slate-400">{{ $issue->message }}</p>
            </div>
            <span class="ml-auto shrink-0 rounded-md px-2 py-1 text-xs font-medium uppercase tracking-wider
                {{ $issue->status === 'open' ? 'bg-rose-500/10 text-rose-400' : 'bg-ink-700 text-slate-400' }}">{{ $issue->status }}</span>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div><p class="text-[11px] uppercase tracking-wider text-slate-500">Events</p><p class="mt-0.5 text-lg font-semibold text-white">{{ Format::num($issue->count) }}</p></div>
            <div><p class="text-[11px] uppercase tracking-wider text-slate-500">Users affected</p><p class="mt-0.5 text-lg font-semibold text-white">{{ Format::num($issue->users_affected) }}</p></div>
            <div><p class="text-[11px] uppercase tracking-wider text-slate-500">First seen</p><p class="mt-0.5 text-sm text-slate-300">{{ Format::ago($issue->first_seen_at) }}</p></div>
            <div><p class="text-[11px] uppercase tracking-wider text-slate-500">Last seen</p><p class="mt-0.5 text-sm text-slate-300">{{ Format::ago($issue->last_seen_at) }}</p></div>
        </div>

        @if($issue->last_trace_id)
            <div class="mt-4 border-t border-ink-700 pt-4">
                <a href="{{ route('warden.trace', [$project->slug, $issue->last_trace_id]) }}"
                   class="inline-flex items-center gap-1.5 text-sm font-medium text-brand-400 hover:text-brand-300">
                    View last trace
                    <span class="font-mono text-[11px] text-slate-500">{{ \Illuminate\Support\Str::limit($issue->last_trace_id, 12, '') }}</span> →
                </a>
            </div>
        @endif
    </div>

    @if(!empty($issue->stack))
        <div class="mt-5 overflow-hidden rounded-xl border border-ink-700 bg-ink-850">
            <div class="border-b border-ink-700 px-4 py-3"><h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Stack trace</h3></div>
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
