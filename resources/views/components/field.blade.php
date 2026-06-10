@props(['label' => null, 'hint' => null, 'for' => null])
<div {{ $attributes }}>
    @if($label)
        <label @if($for) for="{{ $for }}" @endif class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</label>
    @endif
    {{ $slot }}
    @if($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
