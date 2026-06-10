@php use VictorStochero\Warden\Dashboard\Format; @endphp
@include('warden::partials.card-open', ['title' => __('warden::project.cache.title'), 'action' => null])
    @if($stores->isEmpty())
        <p class="px-4 py-10 text-center text-sm text-slate-600">{{ __('warden::project.cache.empty') }}</p>
    @else
        <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="bg-ink-850 text-[11px] uppercase tracking-wider text-slate-500">
                    <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.cache.col_store') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.cache.col_hits') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.cache.col_misses') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.cache.col_writes') }}</th>
                    <th class="px-4 py-3 text-left font-medium pl-6">{{ __('warden::project.cache.col_hit_rate') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-700/70">
                @foreach($stores as $s)
                    <tr class="transition hover:bg-ink-850/50">
                        <td class="px-4 py-3"><span class="font-mono text-[12px] text-slate-200">{{ $s['key'] }}</span></td>
                        <td class="px-4 py-3 text-right text-emerald-400">{{ Format::num($s['hits']) }}</td>
                        <td class="px-4 py-3 text-right text-amber-400">{{ Format::num($s['misses']) }}</td>
                        <td class="px-4 py-3 text-right text-slate-400">{{ Format::num($s['writes']) }}</td>
                        <td class="px-4 py-3 pl-6">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-28 overflow-hidden rounded-full bg-ink-700">
                                    <div class="h-full rounded-full {{ $s['rate'] >= 80 ? 'bg-emerald-500' : ($s['rate'] >= 50 ? 'bg-amber-500' : 'bg-rose-500') }}" style="width: {{ $s['rate'] }}%"></div>
                                </div>
                                <span class="text-xs text-slate-300">{{ $s['rate'] }}%</span>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
@include('warden::partials.card-close')
