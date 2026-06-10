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
    <div class="rounded-2xl border border-dashed border-ink-700/70 bg-ink-900 p-12 text-center text-slate-500">
        {!! __('warden::project.host.empty') !!}
    </div>
@else
    <div class="grid grid-cols-2 gap-5 lg:grid-cols-4">
        @include('warden::partials.kpi', ['label' => __('warden::project.host.cpu'), 'value' => isset($h['cpu']) && $h['cpu'] !== null ? $h['cpu'].'%' : '—', 'tone' => $gauge($h['cpu'] ?? null)])
        @include('warden::partials.kpi', ['label' => __('warden::project.host.memory'), 'value' => isset($h['mem']) && $h['mem'] !== null ? $h['mem'].'%' : '—', 'tone' => $gauge($h['mem'] ?? null)])
        @include('warden::partials.kpi', ['label' => __('warden::project.host.load_1m'), 'value' => $h['load'] ?? '—'])
        @include('warden::partials.kpi', ['label' => __('warden::project.host.disk'), 'value' => isset($h['disk']) && $h['disk'] !== null ? $h['disk'].'%' : '—', 'tone' => $gauge($h['disk'] ?? null)])
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-2">
        <div class="rounded-xl bg-ink-850 ring-1 ring-inset ring-ink-700/50 p-4">
            <p class="mb-3 text-[11px] uppercase tracking-wider text-slate-500">{{ __('warden::project.host.cpu_chart') }}</p>
            @include('warden::partials.chart', ['values' => $cpuSeries, 'color' => '#6366f1', 'height' => 64])
        </div>
        <div class="rounded-xl bg-ink-850 ring-1 ring-inset ring-ink-700/50 p-4">
            <p class="mb-3 text-[11px] uppercase tracking-wider text-slate-500">{{ __('warden::project.host.memory_chart') }}</p>
            @include('warden::partials.chart', ['values' => $memSeries, 'color' => '#10b981', 'height' => 64])
        </div>
    </div>
@endif
