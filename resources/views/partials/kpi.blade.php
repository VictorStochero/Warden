@php
    $tones = [
        'slate'   => 'text-white',
        'emerald' => 'text-emerald-400',
        'amber'   => 'text-amber-400',
        'rose'    => 'text-rose-400',
        'brand'   => 'text-brand-400',
        'sky'     => 'text-sky-400',
    ];
    $link = $link ?? null;
@endphp
<{{ $link ? 'a' : 'div' }}@if($link) href="{{ $link }}"@endif
    class="block rounded-2xl border border-ink-700/70 bg-ink-900 p-5 shadow-lg shadow-black/10{{ $link ? ' transition hover:border-brand-500/40 hover:shadow-brand-500/5' : '' }}">
    <p class="wdn-eyebrow text-[10px] text-slate-500">{{ $label }}</p>
    <p class="mt-2 font-mono text-[28px] font-semibold leading-none tracking-tight {{ $tones[$tone ?? 'slate'] ?? 'text-white' }}">{{ $value }}</p>
    @isset($sub)
        <p class="mt-2 text-xs text-slate-500">{{ $sub }}</p>
    @endisset
</{{ $link ? 'a' : 'div' }}>
