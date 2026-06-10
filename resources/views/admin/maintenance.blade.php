@extends('warden::layout')

@section('title', 'Maintenance')
@section('heading', 'Maintenance')
@section('subheading', 'Trigger parent maintenance commands on demand')

@section('content')
    @if(session('warden_status'))
        <div class="mb-5 rounded-lg border border-emerald-700/50 bg-emerald-900/20 px-4 py-3 text-sm text-emerald-300">
            {{ session('warden_status') }}
        </div>
    @endif
    @if(session('warden_error'))
        <div class="mb-5 rounded-lg border border-rose-700/50 bg-rose-900/20 px-4 py-3 text-sm text-rose-300">
            {{ session('warden_error') }}
        </div>
    @endif

    <p class="mb-5 text-sm text-slate-500">
        Commands run on the queue. No worker? The scheduler already runs these automatically.
    </p>

    <div class="grid gap-4 sm:grid-cols-2">
        @foreach($commands as $command)
            @php $run = $runs[$command] ?? null; @endphp
            <div class="rounded-xl border border-ink-700 bg-ink-850 p-5">
                <div class="flex items-center justify-between">
                    <h3 class="font-mono text-sm text-white">warden:{{ $command }}</h3>
                    @if($run)
                        <span class="text-xs {{ $run->status === 'ok' ? 'text-emerald-400' : ($run->status === 'failed' ? 'text-rose-400' : 'text-slate-400') }}">
                            {{ $run->status }} · {{ $run->finished_at?->diffForHumans() ?? $run->queued_at?->diffForHumans() }}
                        </span>
                    @else
                        <span class="text-xs text-slate-600">never run</span>
                    @endif
                </div>

                <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ $descriptions[$command] ?? '' }}</p>

                @if($run && ($run->duration_ms !== null || $run->message))
                    <div class="mt-3 rounded-lg border border-ink-700 bg-ink-950 p-3">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] uppercase tracking-wider text-slate-600">Last run output</p>
                            @if($run->duration_ms !== null)
                                <p class="text-[11px] text-slate-500">{{ $run->duration_ms }} ms</p>
                            @endif
                        </div>
                        @if($run->message)
                            <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap text-xs {{ $run->status === 'failed' ? 'text-rose-300' : 'text-slate-300' }}">{{ $run->message }}</pre>
                        @else
                            <p class="mt-2 text-xs text-slate-600">Completed with no output.</p>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('warden.admin.maintenance.run') }}" class="mt-4"
                    @if($command === 'prune') data-confirm="Run warden:prune? It permanently deletes raw events and aggregates past their retention window." @endif>
                    @csrf
                    <input type="hidden" name="command" value="{{ $command }}">
                    <button type="submit"
                        class="rounded-lg bg-brand-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-brand-500">
                        Run now
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <div class="mt-8">
        <h2 class="mb-3 text-sm font-semibold text-white">Dropped batches (dead-letter)</h2>
        @if(($deadLetters ?? collect())->isEmpty())
            <p class="text-sm text-slate-600">None — all batches delivered.</p>
        @else
            <div class="overflow-hidden rounded-xl border border-ink-700">
                <table class="min-w-full divide-y divide-ink-700 text-sm">
                    <thead class="bg-ink-850 text-left text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Batch</th>
                            <th class="px-4 py-3">Reason</th>
                            <th class="px-4 py-3">Attempts</th>
                            <th class="px-4 py-3">Reported</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-700 bg-ink-900">
                        @foreach($deadLetters as $dl)
                            <tr>
                                <td class="px-4 py-3 font-mono text-slate-400">{{ $dl->batch_id }}</td>
                                <td class="px-4 py-3 text-rose-300">{{ $dl->reason }}</td>
                                <td class="px-4 py-3 text-slate-400">{{ $dl->attempts }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $dl->reported_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @include('warden::partials.confirm-modal')
@endsection
