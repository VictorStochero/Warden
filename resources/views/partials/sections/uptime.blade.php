@php
    use VictorStochero\Warden\Dashboard\Format;
    use Illuminate\Support\Carbon;
@endphp

<div class="mb-6 grid grid-cols-3 gap-5">
    @foreach($windows as $w)
        <div class="rounded-xl bg-ink-850 p-5 text-center {{ ($w['active'] ?? false) ? 'ring-1 ring-inset ring-brand-600/40 border border-brand-600/60' : 'ring-1 ring-inset ring-ink-700/50' }}">
            <p class="text-3xl font-semibold leading-none {{ $w['pct'] >= 99.5 ? 'text-emerald-400' : ($w['pct'] >= 95 ? 'text-amber-400' : 'text-rose-400') }}">{{ $w['pct'] }}%</p>
            <p class="mt-2 text-[11px] uppercase tracking-wider {{ ($w['active'] ?? false) ? 'text-brand-300' : 'text-slate-500' }}">{{ __('warden::project.uptime.last_window', ['label' => $w['label']]) }}@if($w['active'] ?? false) {{ __('warden::project.uptime.kpi_suffix') }} @endif</p>
        </div>
    @endforeach
</div>

<p class="mb-6 text-sm leading-relaxed text-slate-500">
    {!! __('warden::project.uptime.definition_html', ['errors_url' => route('warden.project.section', [$project->slug, 'errors'])]) !!}
</p>

@include('warden::partials.card-open', ['title' => __('warden::project.uptime.downtime_title'), 'action' => [__('warden::project.uptime.incidents_action'), route('warden.incidents', $project->slug)]])
    @if($incidents->isEmpty())
        <p class="px-4 py-10 text-center text-sm text-emerald-400">{{ __('warden::project.uptime.no_incidents') }}</p>
    @else
        <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead><tr class="bg-ink-850 text-[11px] uppercase tracking-wider text-slate-500">
                <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.uptime.col_incident') }}</th>
                <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.uptime.col_started') }}</th>
                <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.uptime.col_duration') }}</th>
                <th class="px-4 py-3 text-right font-medium">{{ __('warden::project.uptime.col_status') }}</th>
            </tr></thead>
            <tbody class="divide-y divide-ink-700/70">
                @foreach($incidents as $inc)
                    @php $start = Carbon::parse($inc->started_at); $end = $inc->resolved_at ? Carbon::parse($inc->resolved_at) : null; @endphp
                    <tr class="transition hover:bg-ink-850/50">
                        <td class="px-4 py-3">
                            <a href="{{ route('warden.incident', [$project->slug, $inc->id]) }}" class="block max-w-md truncate text-slate-200 hover:text-brand-400">{{ $inc->summary ?? $inc->subject }}</a>
                        </td>
                        <td class="px-4 py-3 text-slate-400">{{ Format::at($inc->started_at, 'd M H:i') }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ $start->diffForHumans($end ?? now(), true) }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="rounded-lg px-1.5 py-0.5 text-[10px] font-medium {{ $inc->status === 'open' ? 'bg-rose-500/10 text-rose-400' : 'bg-emerald-500/10 text-emerald-400' }}">{{ $inc->status === 'open' ? __('warden::project.uptime.status_ongoing') : __('warden::project.uptime.status_resolved') }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    @endif
@include('warden::partials.card-close')
