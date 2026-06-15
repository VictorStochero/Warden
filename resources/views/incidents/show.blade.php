@extends('warden::layout', ['active' => 'incidents', 'showRanges' => false])
@php use VictorStochero\Warden\Dashboard\Format; @endphp

@section('title', __('warden::incidents.show.title'))
@section('heading', $project->name . ' · ' . __('warden::incidents.show.heading'))

@section('content')
    @if(session('warden_status'))
        <div class="mb-5 rounded-xl border border-emerald-700/50 bg-emerald-900/20 px-4 py-3 text-sm text-emerald-300">
            {{ session('warden_status') }}
        </div>
    @endif

    @php
        $isIssue = str_starts_with($incident->subject, 'issue:');
        $isHeartbeat = str_starts_with($incident->subject, 'heartbeat:');
        $issueId = $incident->meta['issue_id'] ?? null;
    @endphp

    <a href="{{ route('warden.incidents', $project->slug) }}" class="mb-4 inline-flex items-center gap-1 text-xs text-slate-500 transition hover:text-slate-300">
        {{ __('warden::incidents.show.back') }}
    </a>

    <div class="rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10 p-6">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ $incident->severity === 'critical' ? 'bg-rose-500/10 text-rose-400' : 'bg-amber-500/10 text-amber-400' }}">{{ $incident->severity }}</span>
                    <span class="rounded-md px-1.5 py-0.5 text-[10px] font-medium {{ $incident->status === 'open' ? 'bg-rose-500/10 text-rose-400' : 'bg-emerald-500/10 text-emerald-400' }}">{{ $incident->status }}</span>
                </div>
                <h2 class="mt-2 text-lg font-semibold text-white">{{ $incident->summary ?? $incident->subject }}</h2>
                <p class="mt-1 font-mono text-xs text-slate-500">{{ $incident->subject }}</p>
            </div>

            @can('manageWarden')
                @if($incident->status === 'open')
                    <form method="POST" action="{{ route('warden.incident.resolve', [$project->slug, $incident->id]) }}"
                        data-confirm="{{ __('warden::incidents.show.resolve_confirm') }}">
                        @csrf
                        <x-warden::button type="submit" variant="secondary" size="sm" class="shrink-0">
                            {{ __('warden::incidents.show.resolve_button') }}
                        </x-warden::button>
                    </form>
                @endif
            @endcan
        </div>

        <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-xs text-slate-500">{{ __('warden::incidents.show.dt_started') }}</dt>
                <dd class="mt-0.5 text-slate-200">{{ Format::at($incident->started_at, 'D, d M Y H:i:s') }}</dd>
                <dd class="text-[11px] text-slate-500">{{ Format::ago($incident->started_at) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">{{ __('warden::incidents.show.dt_resolved') }}</dt>
                <dd class="mt-0.5 text-slate-200">{{ Format::at($incident->resolved_at, 'D, d M Y H:i:s') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">{{ __('warden::incidents.show.dt_last_alerted') }}</dt>
                <dd class="mt-0.5 text-slate-200">{{ $incident->last_alerted_at?->diffForHumans() ?? __('warden::incidents.show.never') }}</dd>
            </div>
        </dl>

        @if($isIssue && $issueId)
            <div class="mt-6 flex flex-wrap items-center gap-4 border-t border-ink-700/70 pt-4">
                <a href="{{ route('warden.issue', [$project->slug, $issueId]) }}" class="inline-flex items-center gap-1.5 text-sm text-brand-400 transition hover:text-brand-300">
                    {{ __('warden::incidents.show.view_related_issue') }}
                </a>
                @if(!empty($errorTraceId))
                    <a href="{{ route('warden.trace', ['project' => $project->slug, 'traceId' => $errorTraceId]) }}" class="inline-flex items-center gap-1.5 text-sm text-brand-400 transition hover:text-brand-300">
                        {{ __('warden::incidents.show.view_error_trace') }}
                    </a>
                @endif
            </div>
        @elseif($isHeartbeat)
            <div class="mt-6 border-t border-ink-700/70 pt-4 text-sm text-slate-400">
                <p>{!! __('warden::incidents.show.heartbeat_description', ['name' => '<span class="font-mono text-slate-300">' . e(\Illuminate\Support\Str::after($incident->subject, 'heartbeat:')) . '</span>']) !!}</p>
                <a href="{{ route('warden.project.section', [$project->slug, 'schedule']) }}" class="mt-1 inline-flex items-center gap-1.5 text-brand-400 transition hover:text-brand-300">{{ __('warden::incidents.show.view_scheduled_tasks') }}</a>
            </div>
        @endif

        @if(!empty($incident->meta))
            <div class="mt-6 border-t border-ink-700/70 pt-4">
                <p class="mb-2 text-[11px] uppercase tracking-wider text-slate-600">{{ __('warden::incidents.show.details_label') }}</p>
                <pre class="overflow-auto rounded-xl bg-ink-950 p-3 text-xs text-slate-300">{{ json_encode($incident->meta, JSON_PRETTY_PRINT) }}</pre>
            </div>
        @endif
    </div>

    @include('warden::partials.confirm-modal')
@endsection
