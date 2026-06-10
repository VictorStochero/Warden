@php use VictorStochero\Warden\Dashboard\Format; @endphp
@if($routes->isEmpty())
    <p class="px-4 py-6 text-center text-sm text-slate-600">No requests in range</p>
@else
    <table class="w-full text-sm">
        <thead>
            <tr class="text-[11px] uppercase tracking-wider text-slate-500">
                <th class="px-4 py-2 text-left font-medium">Route</th>
                <th class="px-4 py-2 text-right font-medium">Count</th>
                <th class="px-4 py-2 text-right font-medium">Errors</th>
                <th class="px-4 py-2 text-right font-medium">Avg</th>
                <th class="px-4 py-2 text-right font-medium">p95</th>
            </tr>
        </thead>
        <tbody>
            @foreach($routes as $r)
                <tr class="border-t border-ink-700/70 hover:bg-ink-800">
                    <td class="px-4 py-2.5"><span class="font-mono text-[12px] text-slate-200">{{ $r['key'] }}</span></td>
                    <td class="px-4 py-2.5 text-right text-slate-300">{{ Format::num($r['count']) }}</td>
                    <td class="px-4 py-2.5 text-right {{ $r['errors'] ? 'text-rose-400' : 'text-slate-600' }}">{{ Format::num($r['errors']) }}</td>
                    <td class="px-4 py-2.5 text-right text-slate-400">{{ Format::dur($r['avg']) }}</td>
                    <td class="px-4 py-2.5 text-right {{ ($r['p95'] ?? 0) >= config('warden.parent.slow_request_ms', 1000) ? 'text-amber-400' : 'text-slate-300' }}">{{ $r['p95'] !== null ? Format::ms($r['p95']) : '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
