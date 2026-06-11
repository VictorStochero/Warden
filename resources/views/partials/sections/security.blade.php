@php
    use VictorStochero\Warden\Dashboard\Format;
    use Illuminate\Support\Str;

    $payload = is_array($audit?->payload ?? null) ? $audit->payload : [];
    $advisories = $payload['advisories'] ?? [];
    $counts = $payload['counts'] ?? [];
    $tools = $payload['tools'] ?? [];
    $severityTone = ['critical' => 'rose', 'high' => 'rose', 'moderate' => 'amber', 'low' => 'sky', 'info' => 'slate', 'unknown' => 'slate'];
    $order = ['critical', 'high', 'moderate', 'low', 'info', 'unknown'];
@endphp

@if(session('warden_status'))
    <div class="mb-4 rounded-xl border border-emerald-700/50 bg-emerald-900/20 px-4 py-2.5 text-sm text-emerald-300">{{ session('warden_status') }}</div>
@endif

@can('manageWarden')
    <div class="mb-4 flex justify-end">
        <form method="POST" action="{{ route('warden.admin.projects.audit-now', $project->id) }}">
            @csrf
            <x-warden::button type="submit" variant="secondary" size="sm">{{ __('warden::project.security.run_audit_btn') }}</x-warden::button>
        </form>
    </div>
@endcan

@if($audit === null)
    <div class="rounded-2xl border border-dashed border-ink-700/70 bg-ink-900 p-12 text-center">
        <p class="text-slate-400">{{ __('warden::project.security.empty_title') }}</p>
        <p class="mt-1 text-sm text-slate-600">{!! __('warden::project.security.empty_hint_html') !!}</p>
    </div>
@else
    <div class="mb-5 flex flex-wrap items-center gap-3 text-sm">
        <span class="text-slate-400">{{ __('warden::project.security.last_audit', ['ago' => Format::ago($audit->occurred_at)]) }}</span>
        @foreach($tools as $tool => $ran)
            <span class="rounded px-1.5 py-0.5 text-[11px] {{ $ran ? 'bg-ink-700 text-slate-300' : 'bg-amber-500/10 text-amber-400' }}">{{ $tool }}: {{ $ran ? __('warden::project.security.tool_ran') : __('warden::project.security.tool_skipped') }}</span>
        @endforeach
    </div>

    <div class="mb-5 grid grid-cols-3 gap-3 sm:grid-cols-6">
        @foreach($order as $sev)
            @php $tone = $severityTone[$sev]; @endphp
            <div class="rounded-xl bg-ink-850 ring-1 ring-inset ring-ink-700/50 p-3 text-center">
                <p class="text-2xl font-semibold leading-none {{ ($counts[$sev] ?? 0) > 0 ? 'text-'.$tone.'-400' : 'text-slate-600' }}">{{ $counts[$sev] ?? 0 }}</p>
                <p class="mt-1 text-[10px] uppercase tracking-wider text-slate-500">{{ $sev }}</p>
            </div>
        @endforeach
    </div>

    @include('warden::partials.card-open', ['title' => __('warden::project.security.vulnerabilities_title', ['total' => $payload['total'] ?? 0]), 'action' => null])
        @if(empty($advisories))
            <p class="px-4 py-10 text-center text-sm text-emerald-400">{{ __('warden::project.security.no_vulnerabilities') }}</p>
        @else
            <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="bg-ink-850 text-[11px] uppercase tracking-wider text-slate-500">
                    <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.security.col_severity') }}</th>
                    <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.security.col_package') }}</th>
                    <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.security.col_advisory') }}</th>
                    <th class="px-4 py-3 text-left font-medium">{{ __('warden::project.security.col_affected') }}</th>
                </tr></thead>
                <tbody class="divide-y divide-ink-700/70">
                    @foreach($advisories as $a)
                        @php $tone = $severityTone[$a['severity'] ?? 'unknown'] ?? 'slate'; @endphp
                        <tr class="transition hover:bg-ink-850/50">
                            <td class="px-4 py-3">
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-{{ $tone }}-400">{{ $a['severity'] ?? 'unknown' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-mono text-[12px] text-slate-200">{{ $a['package'] ?? '?' }}</span>
                                <span class="ml-1 rounded bg-ink-700 px-1 py-0.5 text-[9px] uppercase tracking-wider text-slate-400">{{ $a['ecosystem'] ?? '' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $safeLink = (is_string($a['link'] ?? null) && Str::startsWith(strtolower($a['link']), ['http://', 'https://'])) ? $a['link'] : null;
                                @endphp
                                @if($safeLink)
                                    <a href="{{ $safeLink }}" target="_blank" rel="noopener" class="text-slate-300 hover:text-brand-400">{{ Str::limit($a['title'] ?? ($a['cve'] ?? 'advisory'), 90) }}</a>
                                @else
                                    <span class="text-slate-300">{{ Str::limit($a['title'] ?? ($a['cve'] ?? 'advisory'), 90) }}</span>
                                @endif
                                @if(! empty($a['cve']))<span class="ml-1 font-mono text-[10px] text-slate-500">{{ $a['cve'] }}</span>@endif
                            </td>
                            <td class="px-4 py-3 font-mono text-[11px] text-slate-400">{{ $a['affected'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    @include('warden::partials.card-close')
@endif
