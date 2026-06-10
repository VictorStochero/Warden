@php
    $values = array_values($values ?? []);
    $n = count($values);
    $w = 600; $h = $height ?? 56;
    $max = $n ? max(max($values), 1) : 1;
    $color = $color ?? '#6366f1';
    $pts = [];
    foreach ($values as $i => $v) {
        $x = $n > 1 ? ($i / ($n - 1)) * $w : 0;
        $y = $h - (((float) $v) / $max) * ($h - 6) - 3;
        $pts[] = round($x, 1).','.round($y, 1);
    }
    $line = implode(' ', $pts);
@endphp
@if($n > 0)
    <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" class="w-full" style="height: {{ $h }}px">
        @if(($fill ?? true) && $n > 1)
            <polygon points="0,{{ $h }} {{ $line }} {{ $w }},{{ $h }}" fill="{{ $color }}" opacity="0.12"></polygon>
        @endif
        <polyline points="{{ $line }}" fill="none" stroke="{{ $color }}" stroke-width="2"
                  stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke"></polyline>
    </svg>
@else
    <div class="flex h-[{{ $h }}px] items-center text-xs text-slate-600">no data in range</div>
@endif
