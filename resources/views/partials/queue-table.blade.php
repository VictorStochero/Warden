@php use VictorStochero\Warden\Dashboard\Format; @endphp
@if($queues->isEmpty())
    <p class="px-4 py-8 text-center text-sm text-slate-600">{{ __('warden::project.queue_table.empty') }}</p>
@else
    <div class="overflow-x-auto rounded-2xl border border-ink-700/70">
        <table class="min-w-full text-sm">
            <thead class="bg-ink-850">
                <tr class="text-[11px] uppercase tracking-wider text-slate-500">
                    <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.queue_table.col_job') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.queue_table.col_processed') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.queue_table.col_failed') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.queue_table.col_avg') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-700/70">
                @foreach($queues as $q)
                    <tr class="transition hover:bg-ink-850/50">
                        <td class="px-4 py-3"><span class="font-mono text-[12px] text-slate-200">{{ class_basename($q['key']) }}</span></td>
                        <td class="px-4 py-3 text-right text-slate-300">{{ Format::num($q['processed']) }}</td>
                        <td class="px-4 py-3 text-right {{ $q['failures'] ? 'text-rose-400' : 'text-slate-600' }}">{{ Format::num($q['failures']) }}</td>
                        <td class="px-4 py-3 text-right text-slate-400">{{ Format::dur($q['avg']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
