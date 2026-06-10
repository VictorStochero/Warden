<div class="relative">
    <select {{ $attributes->merge(['class' => 'w-full appearance-none rounded-xl border border-ink-700 bg-ink-850 px-3 py-2 pr-9 text-sm text-white outline-none transition focus:border-brand-500 focus:ring-2 focus:ring-brand-500/30']) }}>
        {{ $slot }}
    </select>
    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6"/></svg>
</div>
