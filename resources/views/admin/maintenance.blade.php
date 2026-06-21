@extends('warden::layout')

@section('title', __('warden::admin.maintenance.title'))
@section('heading', __('warden::admin.maintenance.heading'))
@section('subheading', __('warden::admin.maintenance.subheading'))

@section('content')
    @if(session('warden_status'))
        <div class="mb-5 rounded-xl border border-emerald-700/50 bg-emerald-900/20 px-4 py-3 text-sm text-emerald-300">
            {{ session('warden_status') }}
        </div>
    @endif
    @if(session('warden_error'))
        <div class="mb-5 rounded-xl border border-rose-700/50 bg-rose-900/20 px-4 py-3 text-sm text-rose-300">
            {{ session('warden_error') }}
        </div>
    @endif

    <p class="mb-5 text-sm text-slate-500">
        {{ __('warden::admin.maintenance.intro') }}
    </p>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach($commands as $command)
            @php $run = $runs[$command] ?? null; @endphp
            <div class="rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10 p-5">
                <div class="flex items-center justify-between">
                    <h3 class="font-mono text-sm text-white">warden:{{ $command }}</h3>
                    @if($run)
                        <span class="text-xs {{ $run->status === 'ok' ? 'text-emerald-400' : ($run->status === 'failed' ? 'text-rose-400' : 'text-slate-400') }}">
                            {{ $run->status }} · {{ $run->finished_at?->diffForHumans() ?? $run->queued_at?->diffForHumans() }}
                        </span>
                    @else
                        <span class="text-xs text-slate-600">{{ __('warden::admin.maintenance.never_run') }}</span>
                    @endif
                </div>

                <p class="mt-2 text-xs leading-relaxed text-slate-500">{{ $descriptions[$command] ?? '' }}</p>

                @if($run && ($run->duration_ms !== null || $run->message))
                    <div class="mt-3 rounded-xl bg-ink-850 ring-1 ring-inset ring-ink-700/50 p-3">
                        <div class="flex items-center justify-between">
                            <p class="text-[11px] uppercase tracking-wider text-slate-600">{{ __('warden::admin.maintenance.last_output') }}</p>
                            @if($run->duration_ms !== null)
                                <p class="text-[11px] text-slate-500">{{ $run->duration_ms }} ms</p>
                            @endif
                        </div>
                        @if($run->message)
                            <pre class="mt-2 max-h-40 overflow-auto whitespace-pre-wrap text-xs {{ $run->status === 'failed' ? 'text-rose-300' : 'text-slate-300' }}">{{ $run->message }}</pre>
                        @else
                            <p class="mt-2 text-xs text-slate-600">{{ __('warden::admin.maintenance.no_output') }}</p>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('warden.admin.maintenance.run') }}" class="mt-4"
                    @if($command === 'prune') data-confirm="{{ __('warden::admin.maintenance.confirm_prune') }}" @endif>
                    @csrf
                    <input type="hidden" name="command" value="{{ $command }}">
                    <x-warden::button type="submit" variant="secondary" size="sm">
                        {{ __('warden::admin.maintenance.run_now') }}
                    </x-warden::button>
                </form>
            </div>
        @endforeach
    </div>

    @isset($versionCheck)
        <div class="mt-8 rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10 p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-sm font-semibold text-white">{{ __('warden::version.toggle_label') }}</h2>
                    @if($versionCheck['notice'])
                        <p class="mt-1 text-xs text-brand-300">{{ __('warden::version.available', ['latest' => $versionCheck['notice']['latest'], 'current' => $versionCheck['notice']['current']]) }}</p>
                    @else
                        <p class="mt-1 text-xs text-slate-500">{{ $versionCheck['enabled'] ? '✓' : '—' }}</p>
                    @endif
                    @if($versionCheck['env_locked'])
                        <p class="mt-1 text-[11px] text-amber-200">{{ __('warden::version.env_locked') }}</p>
                    @endif
                </div>
                @unless($versionCheck['env_locked'])
                    <form method="POST" action="{{ route('warden.admin.version-check.toggle') }}">
                        @csrf
                        <input type="hidden" name="enabled" value="{{ $versionCheck['enabled'] ? '0' : '1' }}">
                        <x-warden::button type="submit" variant="secondary" size="sm">
                            {{ $versionCheck['enabled'] ? __('warden::version.disable') : __('warden::version.enable') }}
                        </x-warden::button>
                    </form>
                @endunless
            </div>
        </div>
    @endisset

    <div class="mt-8">
        <h2 class="mb-3 text-sm font-semibold text-white">{{ __('warden::admin.maintenance.dead_letter_title') }}</h2>
        @if(($deadLetters ?? collect())->isEmpty())
            <p class="text-sm text-slate-600">{{ __('warden::admin.maintenance.dead_letter_empty') }}</p>
        @else
            <div class="overflow-x-auto rounded-2xl border border-ink-700/70">
                <table class="min-w-full text-sm">
                    <thead class="bg-ink-850 text-left text-xs uppercase tracking-wider text-slate-500">
                        <tr class="border-b border-ink-700/70">
                            <th class="px-4 py-3">{{ __('warden::admin.maintenance.col_batch') }}</th>
                            <th class="px-4 py-3">{{ __('warden::admin.maintenance.col_reason') }}</th>
                            <th class="px-4 py-3">{{ __('warden::admin.maintenance.col_attempts') }}</th>
                            <th class="px-4 py-3">{{ __('warden::admin.maintenance.col_reported') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-ink-700/70">
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
