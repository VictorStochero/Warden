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
    class="block rounded-xl border border-ink-700 bg-ink-850 p-4{{ $link ? ' transition hover:border-brand-500/50 hover:bg-ink-800' : '' }}">
    <p class="text-[11px] font-medium uppercase tracking-wider text-slate-500">{{ $label }}</p>
    <p class="mt-1.5 text-2xl font-semibold leading-none {{ $tones[$tone ?? 'slate'] ?? 'text-white' }}">{!! $value !!}</p>
    @isset($sub)
        <p class="mt-1.5 text-xs text-slate-500">{!! $sub !!}</p>
    @endisset
</{{ $link ? 'a' : 'div' }}>
