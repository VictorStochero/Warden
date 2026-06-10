<div class="overflow-hidden rounded-xl border border-ink-700 bg-ink-850">
    <div class="flex items-center justify-between border-b border-ink-700 px-4 py-3">
        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $title }}</h3>
        @if(!empty($action))
            <a href="{{ $action[1] }}" class="text-[11px] font-medium text-brand-400 hover:text-brand-300">{{ $action[0] }} →</a>
        @endif
    </div>
    <div>
