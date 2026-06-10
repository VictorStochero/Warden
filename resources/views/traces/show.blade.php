@extends('warden::layout', ['active' => 'traces', 'showRanges' => false])
@php
    use VictorStochero\Warden\Dashboard\Format;

    // Build the waterfall window from the span timings.
    $rows = $spans->map(function ($e) {
        $start = (float) \Illuminate\Support\Carbon::parse($e['occurred_at'])->format('U.u');
        $dur = ($e['duration_us'] ?? 0) / 1_000_000;
        return $e + ['_start' => $start, '_end' => $start + $dur];
    });
    $min = $rows->min('_start') ?: 0;
    $max = $rows->max('_end') ?: ($min + 0.001);
    $span = max($max - $min, 0.000001);

    $typeColor = [
        'request' => '#6366f1', 'query' => '#22d3ee', 'job' => '#10b981', 'cache' => '#a78bfa',
        'http' => '#f59e0b', 'exception' => '#f43f5e', 'log' => '#64748b', 'mail' => '#ec4899',
        'notification' => '#ec4899', 'command' => '#38bdf8', 'schedule' => '#8b5cf6', 'redis' => '#ef4444',
    ];
    $errored = $rows->contains(fn ($e) => $e['type'] === 'exception');
@endphp

@section('title', __('warden::traces.detail.title'))
@section('heading', __('warden::traces.detail.heading'))
@section('subheading', $trace_id)

@section('content')
    <div class="mb-5 flex items-center gap-4">
        <a href="{{ route('warden.traces', $project->slug) }}" class="text-[13px] text-brand-400 hover:text-brand-300">{{ __('warden::traces.detail.back') }}</a>
        <span class="font-mono text-[12px] text-slate-500">{{ $trace_id }}</span>
        <span class="ml-auto text-sm text-slate-400">{{ __('warden::traces.detail.summary', ['count' => $rows->count(), 'duration' => Format::dur((int) round($span * 1_000_000))]) }}</span>
        @if($errored)<span class="rounded bg-rose-500/10 px-2 py-0.5 text-xs font-medium text-rose-400">{{ __('warden::traces.badge.errored') }}</span>@endif
    </div>

    <div class="overflow-hidden rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10">
        <div class="divide-y divide-ink-700/60">
            @foreach($rows as $e)
                @php
                    $left = (($e['_start'] - $min) / $span) * 100;
                    $width = max(0.6, (($e['_end'] - $e['_start']) / $span) * 100);
                    $left = min($left, 99.4);
                    $color = $typeColor[$e['type']] ?? '#64748b';
                    $label = match ($e['type']) {
                        'query' => $e['payload']['sql'] ?? 'query',
                        'request' => ($e['payload']['method'] ?? '').' '.($e['payload']['route'] ?? $e['payload']['path'] ?? ''),
                        'http' => ($e['payload']['method'] ?? '').' '.($e['payload']['host'] ?? ''),
                        'cache' => ($e['payload']['action'] ?? 'cache').' '.($e['payload']['key'] ?? ''),
                        'job' => ($e['payload']['status'] ?? '').' '.($e['payload']['class'] ?? ''),
                        'exception' => $e['payload']['class'] ?? 'exception',
                        'log' => '['.($e['payload']['level'] ?? 'info').'] '.($e['payload']['message'] ?? ''),
                        default => $e['type'],
                    };
                @endphp
                <div class="grid grid-cols-12 items-center gap-3 px-4 py-2 transition hover:bg-ink-850/50">
                    <div class="col-span-5 flex items-center gap-2 min-w-0">
                        <span class="h-2 w-2 shrink-0 rounded-sm" style="background: {{ $color }}"></span>
                        <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wider text-slate-500 w-16">{{ $e['type'] }}</span>
                        <span class="truncate font-mono text-[12px] {{ $e['type'] === 'exception' ? 'text-rose-400' : 'text-slate-300' }}">{{ trim($label) }}</span>
                        @if(!empty($e['n_plus_one']))
                            <span class="shrink-0 rounded bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-400" title="{{ __('warden::traces.detail.n_plus_one_title', ['count' => $e['repeat_count'] ?? '']) }}">{{ __('warden::traces.detail.n_plus_one_label', ['count' => $e['repeat_count'] ?? '']) }}</span>
                        @endif
                    </div>
                    <div class="col-span-6">
                        <div class="relative h-4 rounded bg-ink-900">
                            <div class="absolute top-0 h-4 rounded" style="left: {{ $left }}%; width: {{ $width }}%; background: {{ $color }}; opacity: .85" title="{{ Format::dur($e['duration_us']) }}"></div>
                        </div>
                    </div>
                    <div class="col-span-1 text-right font-mono text-[11px] text-slate-400">{{ Format::dur($e['duration_us']) }}</div>
                </div>
            @endforeach
        </div>
    </div>
@endsection
