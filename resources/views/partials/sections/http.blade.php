@php use VictorStochero\Warden\Dashboard\Format; @endphp
@include('warden::partials.card-open', ['title' => __('warden::project.http.title'), 'action' => null])
    @if($hosts->isEmpty())
        <p class="px-4 py-8 text-center text-sm text-slate-600">{{ __('warden::project.http.empty') }}</p>
    @else
        <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr class="bg-ink-850 text-[11px] uppercase tracking-wider text-slate-500">
                <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.http.col_host') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.http.col_calls') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.http.col_errors') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.http.col_avg') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.http.col_max') }}</th>
            </tr></thead>
            <tbody class="divide-y divide-ink-700/70">
                @foreach($hosts as $h)
                    <tr class="transition hover:bg-ink-850/50">
                        <td class="px-4 py-3 font-mono text-[12px] text-slate-200"><a href="{{ route('warden.traces', ['project' => $project->slug, 'http' => $h['key']]) }}" class="hover:text-brand-400">{{ $h['key'] }}</a></td>
                        <td class="px-4 py-3 text-right text-slate-300">{{ Format::num($h['count']) }}</td>
                        <td class="px-4 py-3 text-right {{ $h['errors'] ? 'text-rose-400' : 'text-slate-600' }}">{{ Format::num($h['errors']) }}</td>
                        <td class="px-4 py-3 text-right text-slate-400">{{ Format::dur($h['avg']) }}</td>
                        <td class="px-4 py-3 text-right text-slate-400">{{ Format::dur($h['max']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
@include('warden::partials.card-close')

<div class="mt-6">
    @include('warden::partials.event-list', ['events' => $recent, 'type' => 'http', 'title' => __('warden::project.http.recent_title')])
</div>
