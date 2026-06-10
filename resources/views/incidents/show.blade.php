@extends('warden::layout', ['active' => 'incidents', 'showRanges' => false])
@php use VictorStochero\Warden\Dashboard\Format; Format::tz($project->timezone ?? null); @endphp

@section('title', 'Incident')
@section('heading', $project->name . ' · Incident')

@section('content')
    @if(session('warden_status'))
        <div class="mb-5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 px-4 py-3 text-sm text-emerald-300">
            {{ session('warden_status') }}
        </div>
    @endif

    @php
        $isIssue = str_starts_with($incident->subject, 'issue:');
        $isHeartbeat = str_starts_with($incident->subject, 'heartbeat:');
        $issueId = $incident->meta['issue_id'] ?? null;
    @endphp

    <a href="{{ route('warden.incidents', $project->slug) }}" class="mb-4 inline-flex items-center gap-1 text-xs text-slate-500 transition hover:text-slate-300">
        ← All incidents
    </a>

    <div class="rounded-xl border border-ink-700 bg-ink-850 p-6">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider {{ $incident->severity === 'critical' ? 'bg-rose-500/10 text-rose-400' : 'bg-amber-500/10 text-amber-400' }}">{{ $incident->severity }}</span>
                    <span class="rounded px-1.5 py-0.5 text-[10px] font-medium {{ $incident->status === 'open' ? 'bg-rose-500/10 text-rose-400' : 'bg-emerald-500/10 text-emerald-400' }}">{{ $incident->status }}</span>
                </div>
                <h2 class="mt-2 text-lg font-semibold text-white">{{ $incident->summary ?? $incident->subject }}</h2>
                <p class="mt-1 font-mono text-xs text-slate-500">{{ $incident->subject }}</p>
            </div>

            @can('manageWarden')
                @if($incident->status === 'open')
                    <form method="POST" action="{{ route('warden.incident.resolve', [$project->slug, $incident->id]) }}"
                        data-confirm="Mark this incident as resolved? If the underlying cause is still active it will reopen on the next evaluation.">
                        @csrf
                        <button class="shrink-0 rounded-lg border border-emerald-700/60 px-3 py-1.5 text-sm text-emerald-300 transition hover:border-emerald-500 hover:text-emerald-200">
                            Resolve
                        </button>
                    </form>
                @endif
            @endcan
        </div>

        <dl class="mt-6 grid grid-cols-2 gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-xs text-slate-500">Started</dt>
                <dd class="mt-0.5 text-slate-200">{{ Format::at($incident->started_at, 'D, d M Y H:i:s') }}</dd>
                <dd class="text-[11px] text-slate-500">{{ Format::ago($incident->started_at) }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Resolved</dt>
                <dd class="mt-0.5 text-slate-200">{{ Format::at($incident->resolved_at, 'D, d M Y H:i:s') }}</dd>
            </div>
            <div>
                <dt class="text-xs text-slate-500">Last alerted</dt>
                <dd class="mt-0.5 text-slate-200">{{ $incident->last_alerted_at?->diffForHumans() ?? 'never' }}</dd>
            </div>
        </dl>

        @if($isIssue && $issueId)
            <div class="mt-6 border-t border-ink-700 pt-4">
                <a href="{{ route('warden.issue', [$project->slug, $issueId]) }}" class="inline-flex items-center gap-1.5 text-sm text-brand-400 transition hover:text-brand-300">
                    View the related issue →
                </a>
            </div>
        @elseif($isHeartbeat)
            <div class="mt-6 border-t border-ink-700 pt-4 text-sm text-slate-400">
                <p>Tracks the heartbeat <span class="font-mono text-slate-300">{{ \Illuminate\Support\Str::after($incident->subject, 'heartbeat:') }}</span> — a scheduled task that stopped reporting on time.</p>
                <a href="{{ route('warden.project.section', [$project->slug, 'schedule']) }}" class="mt-1 inline-flex items-center gap-1.5 text-brand-400 transition hover:text-brand-300">View scheduled tasks →</a>
            </div>
        @endif

        @if(!empty($incident->meta))
            <div class="mt-6 border-t border-ink-700 pt-4">
                <p class="mb-2 text-[11px] uppercase tracking-wider text-slate-600">Details</p>
                <pre class="overflow-auto rounded-lg bg-ink-950 p-3 text-xs text-slate-300">{{ json_encode($incident->meta, JSON_PRETTY_PRINT) }}</pre>
            </div>
        @endif
    </div>

    @include('warden::partials.confirm-modal')
@endsection
