@php use VictorStochero\Warden\Dashboard\Format; @endphp
@php
    $typeColor = ['request' => 'text-brand-400', 'command' => 'text-sky-400', 'schedule' => 'text-violet-400', 'job' => 'text-emerald-400'];
@endphp
@forelse($traces as $t)
    <a href="{{ route('warden.trace', [$project->slug, $t['trace_id']]) }}"
       class="flex items-center gap-3 border-t border-ink-700 px-4 py-2.5 text-sm first:border-0 hover:bg-ink-800">
        <span class="w-14 shrink-0 text-[10px] font-semibold uppercase tracking-wider {{ $typeColor[$t['type']] ?? 'text-slate-500' }}">{{ $t['type'] }}</span>
        <span class="min-w-0 flex-1 truncate {{ $t['errored'] ? 'text-rose-400' : 'text-slate-300' }}">{{ $t['label'] }}</span>
        @if($t['errored'])
            <span class="shrink-0 rounded bg-rose-500/10 px-1.5 py-0.5 text-[10px] font-medium text-rose-400">err</span>
        @endif
        <span class="shrink-0 font-mono text-[11px] text-slate-500">{{ Format::dur($t['duration_us']) }}</span>
    </a>
@empty
    <p class="px-4 py-6 text-center text-sm text-slate-600">No traces captured</p>
@endforelse
