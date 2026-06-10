@php
    use VictorStochero\Warden\Dashboard\Format;
    use Illuminate\Support\Carbon;

    $d = $delivery;
    $cad = $d['cadence'];

    if ($cad === null) {
        $mode = 'No data'; $modeTone = 'slate';
    } elseif ($cad <= 10) {
        $mode = 'Continuous · daemon'; $modeTone = 'emerald';
    } elseif ($cad >= 45 && $cad <= 90) {
        $mode = 'Every minute · cron'; $modeTone = 'sky';
    } else {
        $mode = '~every ' . $cad . 's'; $modeTone = 'amber';
    }

    $live = $d['last'] !== null && Carbon::parse($d['last'])->gt(now()->subMinutes(2));
@endphp

<div class="mb-5 grid grid-cols-2 gap-4 sm:grid-cols-4">
    <div class="rounded-xl border border-ink-700 bg-ink-850 p-4">
        <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">Last received</p>
        <p class="mt-1.5 flex items-center gap-2 text-2xl font-semibold leading-none text-white">
            @if($live)<span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>@endif
            {{ Format::ago($d['last']) }}
        </p>
        <p class="mt-1.5 text-xs text-slate-500">{{ $d['last'] ? Format::at($d['last']) : 'never' }}</p>
    </div>
    @include('warden::partials.kpi', ['label' => 'Delivery mode', 'value' => $mode, 'tone' => $modeTone, 'sub' => 'inferred from arrival gaps'])
    @include('warden::partials.kpi', ['label' => 'Batches', 'value' => Format::num($d['batches']), 'sub' => 'last ' . $d['window'] . 'm'])
    @include('warden::partials.kpi', ['label' => 'Events', 'value' => Format::num($d['events']), 'sub' => 'last ' . $d['window'] . 'm'])
</div>

<div class="mb-5 rounded-xl border border-ink-700 bg-ink-850 p-4">
    <div class="mb-3 flex items-center justify-between">
        <span class="text-xs font-semibold uppercase tracking-wider text-slate-500">Arrivals per minute · last {{ $d['window'] }}m</span>
        <span class="text-xs text-slate-500">{{ $live ? 'receiving' : 'idle' }}</span>
    </div>
    @include('warden::partials.bars', ['values' => $d['series'], 'color' => '#34d399', 'height' => 56])
</div>

@include('warden::partials.card-open', ['title' => 'Recent arrivals', 'action' => null])
    @if($d['recent']->isEmpty())
        <p class="px-4 py-10 text-center text-sm text-slate-600">Nothing received in the last {{ $d['window'] }} minutes. If the child is configured, check its <span class="font-mono">warden:ship</span> daemon or scheduler.</p>
    @else
        <table class="w-full text-sm">
            <thead><tr class="border-b border-ink-700 text-[11px] uppercase tracking-wider text-slate-500">
                <th class="px-4 py-2 text-left font-medium">Received</th>
                <th class="px-4 py-2 text-left font-medium">When</th>
                <th class="px-4 py-2 text-right font-medium">Batches</th>
                <th class="px-4 py-2 text-right font-medium">Events</th>
            </tr></thead>
            <tbody>
                @foreach($d['recent'] as $r)
                    <tr class="border-t border-ink-700/70 hover:bg-ink-800">
                        <td class="px-4 py-2.5 font-mono text-[12px] text-slate-300">{{ Format::time($r->received_at) }}</td>
                        <td class="px-4 py-2.5 text-slate-500">{{ Format::ago($r->received_at) }}</td>
                        <td class="px-4 py-2.5 text-right text-slate-300">{{ Format::num($r->batches) }}</td>
                        <td class="px-4 py-2.5 text-right text-slate-400">{{ Format::num($r->events) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@include('warden::partials.card-close')
