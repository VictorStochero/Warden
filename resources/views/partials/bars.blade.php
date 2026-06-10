@php
    $values = array_values($values ?? []);
    $n = count($values);
    $max = $n ? max(max($values), 1) : 1;
    $color = $color ?? '#6366f1';
@endphp
@if($n > 0)
    <div class="flex items-end gap-[2px]" style="height: {{ $height ?? 56 }}px">
        @foreach($values as $v)
            <div class="flex-1 rounded-sm transition-all"
                 style="height: {{ max(2, (((float) $v) / $max) * 100) }}%; background: {{ $color }}; opacity: {{ $v > 0 ? 0.85 : 0.18 }}"
                 title="{{ $v }}"></div>
        @endforeach
    </div>
@else
    <div class="flex items-center text-xs text-slate-600" style="height: {{ $height ?? 56 }}px">no data in range</div>
@endif
