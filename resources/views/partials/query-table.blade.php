@php use VictorStochero\Warden\Dashboard\Format; @endphp
@if($queries->isEmpty())
    <p class="px-4 py-6 text-center text-sm text-slate-600">No queries in range</p>
@else
    <table class="w-full text-sm">
        <thead>
            <tr class="text-[11px] uppercase tracking-wider text-slate-500">
                <th class="px-4 py-2 text-left font-medium">Query</th>
                <th class="px-4 py-2 text-right font-medium">Calls</th>
                <th class="px-4 py-2 text-right font-medium">Avg</th>
                <th class="px-4 py-2 text-right font-medium">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($queries as $q)
                <tr class="border-t border-ink-700/70 align-top hover:bg-ink-800">
                    <td class="px-4 py-2.5">
                        <p class="line-clamp-2 max-w-xl font-mono text-[12px] leading-snug text-slate-300">{{ $q['sql'] }}</p>
                        @if(($q['slow'] ?? 0) > 0)
                            <span class="mt-1 inline-block rounded bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-400">{{ Format::num($q['slow']) }} slow</span>
                        @endif
                    </td>
                    <td class="px-4 py-2.5 text-right text-slate-300">{{ Format::num($q['count']) }}</td>
                    <td class="px-4 py-2.5 text-right {{ $q['avg'] >= config('warden.parent.slow_query_ms', 100) * 1000 ? 'text-amber-400' : 'text-slate-400' }}">{{ Format::dur($q['avg']) }}</td>
                    <td class="px-4 py-2.5 text-right text-slate-400">{{ Format::dur($q['total']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
