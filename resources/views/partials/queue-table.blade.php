@php use VictorStochero\Warden\Dashboard\Format; @endphp
@if($queues->isEmpty())
    <p class="px-4 py-6 text-center text-sm text-slate-600">No jobs in range</p>
@else
    <table class="w-full text-sm">
        <thead>
            <tr class="text-[11px] uppercase tracking-wider text-slate-500">
                <th class="px-4 py-2 text-left font-medium">Job</th>
                <th class="px-4 py-2 text-right font-medium">Processed</th>
                <th class="px-4 py-2 text-right font-medium">Failed</th>
                <th class="px-4 py-2 text-right font-medium">Avg</th>
            </tr>
        </thead>
        <tbody>
            @foreach($queues as $q)
                <tr class="border-t border-ink-700/70 hover:bg-ink-800">
                    <td class="px-4 py-2.5"><span class="font-mono text-[12px] text-slate-200">{{ class_basename($q['key']) }}</span></td>
                    <td class="px-4 py-2.5 text-right text-slate-300">{{ Format::num($q['processed']) }}</td>
                    <td class="px-4 py-2.5 text-right {{ $q['failures'] ? 'text-rose-400' : 'text-slate-600' }}">{{ Format::num($q['failures']) }}</td>
                    <td class="px-4 py-2.5 text-right text-slate-400">{{ Format::dur($q['avg']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
