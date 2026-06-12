@php
    use VictorStochero\Warden\Dashboard\Format;
    use VictorStochero\Warden\Support\Cast;

    $h = $latest ?? [];
    $cpuSeries = $series->map(fn ($r) => $r['cpu'] ?? 0)->all();
    $memSeries = $series->map(fn ($r) => $r['mem'] ?? 0)->all();
    $gauge = function ($v) {
        if ($v === null) return 'slate';
        return $v >= 90 ? 'rose' : ($v >= 70 ? 'amber' : 'emerald');
    };

    // Newest raw sample (untrusted child data): every value is coerced to a
    // number here; the only strings rendered are escaped via {{ }}.
    $p = Cast::arr($detail->payload ?? null);
    $mem = Cast::arr($p['memory'] ?? null);
    $dsk = Cast::arr($p['disk'] ?? null);
    $loadNow = Cast::arr($p['load'] ?? null);
    $procs = array_values(array_filter(Cast::arr($p['processes'] ?? null), 'is_array'));

    $num = fn ($v) => is_numeric($v) ? (float) $v : null;
    $byte = fn ($v) => is_numeric($v) ? Format::bytes((int) $v) : '—';
    $pct = fn ($v) => is_numeric($v) ? round((float) $v, 1).'%' : '—';
    $bar = fn ($v) => max(0, min(100, is_numeric($v) ? (float) $v : 0));
    $barColor = fn ($v) => $v >= 90 ? 'bg-rose-500' : ($v >= 70 ? 'bg-amber-500' : 'bg-emerald-500');
@endphp

@if(empty($h) && empty($p))
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

    @if($p !== [])
        <div class="mt-6 grid grid-cols-1 gap-5 lg:grid-cols-3">
            {{-- CPU --}}
            <div class="rounded-xl bg-ink-850 ring-1 ring-inset ring-ink-700/50 p-4">
                <p class="mb-3 text-[11px] uppercase tracking-wider text-slate-500">{{ __('warden::project.host.details_cpu') }}</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('warden::project.host.usage') }}</dt><dd class="text-slate-200">{{ $pct($p['cpu'] ?? null) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('warden::project.host.cores') }}</dt><dd class="text-slate-200">{{ is_numeric($p['cores'] ?? null) ? (int) $p['cores'] : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('warden::project.host.load_avg') }}</dt>
                        <dd class="font-mono text-[12px] text-slate-200">{{ $num($loadNow[1] ?? null) ?? '—' }} / {{ $num($loadNow[5] ?? null) ?? '—' }} / {{ $num($loadNow[15] ?? null) ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('warden::project.host.sampled_at') }}</dt><dd class="text-slate-400">{{ Format::at($detail->occurred_at ?? null, 'H:i:s') }}</dd></div>
                </dl>
            </div>

            {{-- Memory --}}
            <div class="rounded-xl bg-ink-850 ring-1 ring-inset ring-ink-700/50 p-4">
                <p class="mb-3 text-[11px] uppercase tracking-wider text-slate-500">{{ __('warden::project.host.details_memory') }}</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('warden::project.host.used') }}</dt><dd class="text-slate-200">{{ $byte($mem['used'] ?? null) }} ({{ $pct($mem['used_percent'] ?? null) }})</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('warden::project.host.available') }}</dt><dd class="text-slate-200">{{ $byte($mem['available'] ?? null) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('warden::project.host.total') }}</dt><dd class="text-slate-200">{{ $byte($mem['total'] ?? null) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('warden::project.host.swap') }}</dt>
                        <dd class="text-slate-200">{{ is_numeric($mem['swap_total'] ?? null) && (int) $mem['swap_total'] > 0 ? $byte($mem['swap_used'] ?? null).' / '.$byte($mem['swap_total']) : '—' }}</dd></div>
                </dl>
                @if(is_numeric($mem['used_percent'] ?? null))
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-ink-700/70">
                        <div class="h-full rounded-full {{ $barColor($bar($mem['used_percent'])) }}" style="width: {{ $bar($mem['used_percent']) }}%"></div>
                    </div>
                @endif
            </div>

            {{-- Disk --}}
            <div class="rounded-xl bg-ink-850 ring-1 ring-inset ring-ink-700/50 p-4">
                <p class="mb-3 text-[11px] uppercase tracking-wider text-slate-500">{{ __('warden::project.host.details_disk') }}</p>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('warden::project.host.used') }}</dt><dd class="text-slate-200">{{ $byte($dsk['used'] ?? null) }} ({{ $pct($dsk['used_percent'] ?? null) }})</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('warden::project.host.free') }}</dt><dd class="text-slate-200">{{ $byte($dsk['free'] ?? null) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">{{ __('warden::project.host.total') }}</dt><dd class="text-slate-200">{{ $byte($dsk['total'] ?? null) }}</dd></div>
                </dl>
                @if(is_numeric($dsk['used_percent'] ?? null))
                    <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-ink-700/70">
                        <div class="h-full rounded-full {{ $barColor($bar($dsk['used_percent'])) }}" style="width: {{ $bar($dsk['used_percent']) }}%"></div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Top processes --}}
        <div class="mt-6">
            @include('warden::partials.card-open', ['title' => __('warden::project.host.processes_title'), 'action' => null])
                @if($procs === [])
                    <p class="px-4 py-8 text-center text-sm text-slate-600">{{ __('warden::project.host.processes_empty') }}</p>
                @else
                    <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead><tr class="bg-ink-850 text-[11px] uppercase tracking-wider text-slate-500">
                            <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.host.col_process') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.host.col_pid') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.host.col_cpu') }}</th>
                            <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.host.col_memory') }}</th>
                        </tr></thead>
                        <tbody class="divide-y divide-ink-700/70">
                            @foreach($procs as $proc)
                                @php $procCpu = $num($proc['cpu'] ?? null); @endphp
                                <tr class="transition hover:bg-ink-850/50">
                                    <td class="px-4 py-3 font-mono text-[12px] text-slate-200">{{ Cast::str($proc['name'] ?? null, '—') }}</td>
                                    <td class="px-4 py-3 text-right text-slate-400">{{ is_numeric($proc['pid'] ?? null) ? (int) $proc['pid'] : '—' }}</td>
                                    <td class="px-4 py-3 text-right {{ $procCpu !== null && $procCpu >= 50 ? 'text-amber-400' : 'text-slate-300' }}">{{ $pct($procCpu) }}</td>
                                    <td class="px-4 py-3 text-right text-slate-300">{{ $byte($proc['memory'] ?? null) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            @include('warden::partials.card-close')
        </div>
    @endif

    <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2">
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
