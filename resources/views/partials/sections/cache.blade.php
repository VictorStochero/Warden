@php use VictorStochero\Warden\Dashboard\Format; @endphp
@include('warden::partials.card-open', ['title' => 'Cache stores', 'action' => null])
    @if($stores->isEmpty())
        <p class="px-4 py-6 text-center text-sm text-slate-600">No cache activity in range</p>
    @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-[11px] uppercase tracking-wider text-slate-500">
                    <th class="px-4 py-2 text-left font-medium">Store</th>
                    <th class="px-4 py-2 text-right font-medium">Hits</th>
                    <th class="px-4 py-2 text-right font-medium">Misses</th>
                    <th class="px-4 py-2 text-right font-medium">Writes</th>
                    <th class="px-4 py-2 text-left font-medium pl-6">Hit rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stores as $s)
                    <tr class="border-t border-ink-700/70 hover:bg-ink-800">
                        <td class="px-4 py-2.5"><span class="font-mono text-[12px] text-slate-200">{{ $s['key'] }}</span></td>
                        <td class="px-4 py-2.5 text-right text-emerald-400">{{ Format::num($s['hits']) }}</td>
                        <td class="px-4 py-2.5 text-right text-amber-400">{{ Format::num($s['misses']) }}</td>
                        <td class="px-4 py-2.5 text-right text-slate-400">{{ Format::num($s['writes']) }}</td>
                        <td class="px-4 py-2.5 pl-6">
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
    @endif
@include('warden::partials.card-close')
