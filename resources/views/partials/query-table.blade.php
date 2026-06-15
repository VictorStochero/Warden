@php use VictorStochero\Warden\Dashboard\Format; use Illuminate\Support\Str; @endphp
@if($queries->isEmpty())
    <p class="px-4 py-8 text-center text-sm text-slate-600">{{ __('warden::project.query_table.empty') }}</p>
@else
    <div class="overflow-x-auto rounded-2xl border border-ink-700/70">
        <table class="min-w-full text-sm">
            <thead class="bg-ink-850">
                <tr class="text-[11px] uppercase tracking-wider text-slate-500">
                    <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.query_table.col_query') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.query_table.col_calls') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.query_table.col_avg') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.query_table.col_total') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-700/70">
                @foreach($queries as $q)
                    <tr class="align-top transition hover:bg-ink-850/50">
                        <td class="px-4 py-3">
                            <a href="{{ route('warden.traces', ['project' => $project->slug, 'query' => Str::after($q['key'], 'q_')]) }}" class="block">
                                <p class="line-clamp-2 max-w-xl font-mono text-[12px] leading-snug text-slate-300 hover:text-brand-400">{{ $q['sql'] }}</p>
                            </a>
                            @if(($q['slow'] ?? 0) > 0)
                                <span class="mt-1 inline-block rounded bg-amber-500/10 px-1.5 py-0.5 text-[10px] font-medium text-amber-400">{{ __('warden::project.query_table.slow_badge', ['count' => Format::num($q['slow'])]) }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right text-slate-300">{{ Format::num($q['count']) }}</td>
                        <td class="px-4 py-3 text-right {{ $q['avg'] >= config('warden.parent.slow_query_ms', 100) * 1000 ? 'text-amber-400' : 'text-slate-400' }}">{{ Format::dur($q['avg']) }}</td>
                        <td class="px-4 py-3 text-right text-slate-400">{{ Format::dur($q['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
