@props([
    'variant' => 'primary',
    'size' => 'md',
    'href' => null,
])
@php
    // Warden Design System button: beacon-blue primary with glow, quiet
    // secondary/ghost for supporting actions, danger for destructive.
    $base = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-lg font-semibold transition duration-150 hover:brightness-110 active:translate-y-px disabled:cursor-not-allowed disabled:opacity-45';

    $sizes = [
        'sm' => 'h-[30px] gap-1.5 px-3 text-[13px]',
        'md' => 'h-[38px] px-4 text-sm',
        'lg' => 'h-[46px] gap-2.5 px-[22px] text-[15px]',
    ];

    $variants = [
        'primary' => 'bg-brand-500 text-white shadow-glow',
        'secondary' => 'border border-ink-600 bg-ink-750 text-slate-200 shadow-[inset_0_1px_0_rgba(255,255,255,0.05)]',
        'ghost' => 'text-slate-400 hover:bg-ink-800 hover:text-white hover:brightness-100',
        'danger' => 'bg-rose-500 text-white',
        'danger-soft' => 'border border-rose-700/60 bg-rose-500/5 text-rose-300 hover:border-rose-500 hover:text-rose-200 hover:brightness-100',
    ];

    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp
@if($href !== null)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $classes]) }}>{{ $slot }}</button>
@endif
