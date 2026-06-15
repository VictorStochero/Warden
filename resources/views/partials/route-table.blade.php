@php use VictorStochero\Warden\Dashboard\Format; @endphp
@if($routes->isEmpty())
    <p class="px-4 py-8 text-center text-sm text-slate-600">{{ __('warden::project.route_table.empty') }}</p>
@else
    <div class="overflow-x-auto rounded-2xl border border-ink-700/70">
        <table class="min-w-full text-sm">
            <thead class="bg-ink-850">
                <tr class="text-[11px] uppercase tracking-wider text-slate-500">
                    <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.route_table.col_route') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.route_table.col_count') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.route_table.col_errors') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.route_table.col_avg') }}</th>
                    <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.route_table.col_p95') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-ink-700/70">
                @foreach($routes as $r)
                    <tr class="transition hover:bg-ink-850/50">
                        <td class="px-4 py-3"><a href="{{ route('warden.traces', ['project' => $project->slug, 'route' => $r['key']]) }}" class="font-mono text-[12px] text-slate-200 hover:text-brand-400">{{ $r['key'] }}</a></td>
                        <td class="px-4 py-3 text-right text-slate-300">{{ Format::num($r['count']) }}</td>
                        <td class="px-4 py-3 text-right {{ $r['errors'] ? 'text-rose-400' : 'text-slate-600' }}">{{ Format::num($r['errors']) }}</td>
                        <td class="px-4 py-3 text-right text-slate-400">{{ Format::dur($r['avg']) }}</td>
                        <td class="px-4 py-3 text-right {{ ($r['p95'] ?? 0) >= config('warden.parent.slow_request_ms', 1000) ? 'text-amber-400' : 'text-slate-300' }}">{{ $r['p95'] !== null ? Format::ms($r['p95']) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
