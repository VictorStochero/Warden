@php use VictorStochero\Warden\Dashboard\Format; @endphp
@include('warden::partials.card-open', ['title' => 'Outgoing HTTP', 'action' => null])
    @if($hosts->isEmpty())
        <p class="px-4 py-6 text-center text-sm text-slate-600">No outgoing requests in range</p>
    @else
        <table class="w-full text-sm">
            <thead><tr class="text-[11px] uppercase tracking-wider text-slate-500">
                <th class="px-4 py-2 text-left font-medium">Host</th>
                <th class="px-4 py-2 text-right font-medium">Calls</th>
                <th class="px-4 py-2 text-right font-medium">Errors</th>
                <th class="px-4 py-2 text-right font-medium">Avg</th>
                <th class="px-4 py-2 text-right font-medium">Max</th>
            </tr></thead>
            <tbody>
                @foreach($hosts as $h)
                    <tr class="border-t border-ink-700/70 hover:bg-ink-800">
                        <td class="px-4 py-2.5 font-mono text-[12px] text-slate-200">{{ $h['key'] }}</td>
                        <td class="px-4 py-2.5 text-right text-slate-300">{{ Format::num($h['count']) }}</td>
                        <td class="px-4 py-2.5 text-right {{ $h['errors'] ? 'text-rose-400' : 'text-slate-600' }}">{{ Format::num($h['errors']) }}</td>
                        <td class="px-4 py-2.5 text-right text-slate-400">{{ Format::dur($h['avg']) }}</td>
                        <td class="px-4 py-2.5 text-right text-slate-400">{{ Format::dur($h['max']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@include('warden::partials.card-close')

<div class="mt-5">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'http', 'title' => 'Recent outgoing calls'])
</div>
