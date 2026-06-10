@php
    use VictorStochero\Warden\Dashboard\Format;
    use Illuminate\Support\Carbon;
@endphp

<div class="mb-5 grid grid-cols-3 gap-4">
    @foreach($windows as $w)
        <div class="rounded-xl border bg-ink-850 p-5 text-center {{ ($w['active'] ?? false) ? 'border-brand-600/60 ring-1 ring-brand-600/30' : 'border-ink-700' }}">
            <p class="text-3xl font-semibold leading-none {{ $w['pct'] >= 99.5 ? 'text-emerald-400' : ($w['pct'] >= 95 ? 'text-amber-400' : 'text-rose-400') }}">{{ $w['pct'] }}%</p>
            <p class="mt-2 text-[11px] uppercase tracking-wider {{ ($w['active'] ?? false) ? 'text-brand-300' : 'text-slate-500' }}">last {{ $w['label'] }}@if($w['active'] ?? false) · KPI @endif</p>
        </div>
    @endforeach
</div>

<p class="mb-5 text-sm leading-relaxed text-slate-500">
    Availability = share of time with no open <span class="text-slate-300">critical</span> incident
    (a down heartbeat or a high-severity issue). HTTP errors alone don't reduce uptime — see
    <a href="{{ route('warden.project.section', [$project->slug, 'errors']) }}" class="text-brand-400 hover:text-brand-300">Errors</a>.
</p>

@include('warden::partials.card-open', ['title' => 'Downtime episodes · 30d', 'action' => ['Incidents', route('warden.incidents', $project->slug)]])
    @if($incidents->isEmpty())
        <p class="px-4 py-10 text-center text-sm text-emerald-400">No critical incidents in the last 30 days 🎉</p>
    @else
        <table class="w-full text-sm">
            <thead><tr class="border-b border-ink-700 text-[11px] uppercase tracking-wider text-slate-500">
                <th class="px-4 py-2 text-left font-medium">Incident</th>
                <th class="px-4 py-2 text-left font-medium">Started</th>
                <th class="px-4 py-2 text-left font-medium">Duration</th>
                <th class="px-4 py-2 text-right font-medium">Status</th>
            </tr></thead>
            <tbody>
                @foreach($incidents as $inc)
                    @php $start = Carbon::parse($inc->started_at); $end = $inc->resolved_at ? Carbon::parse($inc->resolved_at) : null; @endphp
                    <tr class="border-t border-ink-700/70 hover:bg-ink-800">
                        <td class="px-4 py-2.5">
                            <a href="{{ route('warden.incident', [$project->slug, $inc->id]) }}" class="block max-w-md truncate text-slate-200 hover:text-brand-400">{{ $inc->summary ?? $inc->subject }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-slate-400">{{ Format::at($inc->started_at, 'd M H:i') }}</td>
                        <td class="px-4 py-2.5 text-slate-400">{{ $start->diffForHumans($end ?? now(), true) }}</td>
                        <td class="px-4 py-2.5 text-right">
                            <span class="rounded px-1.5 py-0.5 text-[10px] font-medium {{ $inc->status === 'open' ? 'bg-rose-500/10 text-rose-400' : 'bg-emerald-500/10 text-emerald-400' }}">{{ $inc->status === 'open' ? 'ongoing' : 'resolved' }}</span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@include('warden::partials.card-close')
