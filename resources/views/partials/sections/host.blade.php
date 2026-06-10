@php
    use VictorStochero\Warden\Dashboard\Format;
    $h = $latest ?? [];
    $cpuSeries = $series->map(fn ($r) => $r['cpu'] ?? 0)->all();
    $memSeries = $series->map(fn ($r) => $r['mem'] ?? 0)->all();
    $gauge = function ($v) {
        if ($v === null) return 'slate';
        return $v >= 90 ? 'rose' : ($v >= 70 ? 'amber' : 'emerald');
    };
@endphp

@if(empty($h))
    <div class="rounded-xl border border-dashed border-ink-700 bg-ink-900 p-12 text-center text-slate-500">
        No host metrics in range. The host recorder samples <code class="text-brand-400">/proc</code> on Linux.
    </div>
@else
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        @include('warden::partials.kpi', ['label' => 'CPU', 'value' => isset($h['cpu']) && $h['cpu'] !== null ? $h['cpu'].'%' : '—', 'tone' => $gauge($h['cpu'] ?? null)])
        @include('warden::partials.kpi', ['label' => 'Memory', 'value' => isset($h['mem']) && $h['mem'] !== null ? $h['mem'].'%' : '—', 'tone' => $gauge($h['mem'] ?? null)])
        @include('warden::partials.kpi', ['label' => 'Load (1m)', 'value' => $h['load'] ?? '—'])
        @include('warden::partials.kpi', ['label' => 'Disk', 'value' => isset($h['disk']) && $h['disk'] !== null ? $h['disk'].'%' : '—', 'tone' => $gauge($h['disk'] ?? null)])
    </div>

    <div class="mt-5 grid gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-ink-700 bg-ink-850 p-4">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">CPU %</p>
            @include('warden::partials.chart', ['values' => $cpuSeries, 'color' => '#6366f1', 'height' => 64])
        </div>
        <div class="rounded-xl border border-ink-700 bg-ink-850 p-4">
            <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">Memory %</p>
            @include('warden::partials.chart', ['values' => $memSeries, 'color' => '#10b981', 'height' => 64])
        </div>
    </div>
@endif
