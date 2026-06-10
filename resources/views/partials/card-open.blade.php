<div class="overflow-hidden rounded-2xl border border-ink-700/70 bg-ink-900 shadow-lg shadow-black/10">
    <div class="flex items-center justify-between border-b border-ink-700/70 px-5 py-3.5">
        <h3 class="wdn-eyebrow text-[11px] text-slate-400">{{ $title }}</h3>
        @if(!empty($action))
            <a href="{{ $action[1] }}" class="text-[11px] font-medium text-brand-400 transition hover:text-brand-300">{{ $action[0] }} →</a>
        @endif
    </div>
    <div>
