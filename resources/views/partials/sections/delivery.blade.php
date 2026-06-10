@php
    use VictorStochero\Warden\Dashboard\Format;
    use Illuminate\Support\Carbon;

    $d = $delivery;
    $cad = $d['cadence'];

    if ($cad === null) {
        $mode = __('warden::project.delivery.mode_no_data'); $modeTone = 'slate';
    } elseif ($cad <= 10) {
        $mode = __('warden::project.delivery.mode_continuous'); $modeTone = 'emerald';
    } elseif ($cad >= 45 && $cad <= 90) {
        $mode = __('warden::project.delivery.mode_every_minute'); $modeTone = 'sky';
    } else {
        $mode = __('warden::project.delivery.mode_approx', ['cads' => $cad . 's']); $modeTone = 'amber';
    }

    $live = $d['last'] !== null && Carbon::parse($d['last'])->gt(now()->subMinutes(2));
@endphp

<div class="mb-6 grid grid-cols-2 gap-5 sm:grid-cols-4">
    <div class="rounded-xl bg-ink-850 ring-1 ring-inset ring-ink-700/50 p-4">
        <p class="text-[11px] uppercase tracking-wider text-slate-500">{{ __('warden::project.delivery.last_received') }}</p>
        <p class="mt-1.5 flex items-center gap-2 text-2xl font-semibold leading-none text-white">
            @if($live)<span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>@endif
            {{ Format::ago($d['last']) }}
        </p>
        <p class="mt-1.5 text-xs text-slate-500">{{ $d['last'] ? Format::at($d['last']) : __('warden::project.delivery.never') }}</p>
    </div>
    @include('warden::partials.kpi', ['label' => __('warden::project.delivery.mode_label'), 'value' => $mode, 'tone' => $modeTone, 'sub' => __('warden::project.delivery.mode_sub')])
    @include('warden::partials.kpi', ['label' => __('warden::project.delivery.batches_label'), 'value' => Format::num($d['batches']), 'sub' => __('warden::project.delivery.last_window', ['window' => $d['window']])])
    @include('warden::partials.kpi', ['label' => __('warden::project.delivery.events_label'), 'value' => Format::num($d['events']), 'sub' => __('warden::project.delivery.last_window', ['window' => $d['window']])])
</div>

<div class="mb-6 rounded-xl bg-ink-850 ring-1 ring-inset ring-ink-700/50 p-4">
    <div class="mb-3 flex items-center justify-between">
        <span class="text-[11px] uppercase tracking-wider text-slate-500">{{ __('warden::project.delivery.arrivals_chart_label', ['window' => $d['window']]) }}</span>
        <span class="text-xs text-slate-500">{{ $live ? __('warden::project.delivery.status_receiving') : __('warden::project.delivery.status_idle') }}</span>
    </div>
    @include('warden::partials.bars', ['values' => $d['series'], 'color' => '#34d399', 'height' => 56])
</div>

@include('warden::partials.card-open', ['title' => __('warden::project.delivery.recent_arrivals'), 'action' => null])
    @if($d['recent']->isEmpty())
        <p class="px-4 py-10 text-center text-sm text-slate-600">{!! __('warden::project.delivery.arrivals_empty', ['window' => $d['window']]) !!}</p>
    @else
        <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr class="bg-ink-850 text-[11px] uppercase tracking-wider text-slate-500">
                <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.delivery.col_received') }}</th>
                <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.delivery.col_when') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.delivery.col_batches') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.delivery.col_events') }}</th>
            </tr></thead>
            <tbody class="divide-y divide-ink-700/70">
                @foreach($d['recent'] as $r)
                    <tr class="transition hover:bg-ink-850/50">
                        <td class="px-4 py-3 font-mono text-[12px] text-slate-300">{{ Format::time($r->received_at) }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ Format::ago($r->received_at) }}</td>
                        <td class="px-4 py-3 text-right text-slate-300">{{ Format::num($r->batches) }}</td>
                        <td class="px-4 py-3 text-right text-slate-400">{{ Format::num($r->events) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
@include('warden::partials.card-close')
