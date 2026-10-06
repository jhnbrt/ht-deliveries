@props(['segments', 'total'])
@php
    $r = 70; $sw = 34; $gap = 10;
    $c = 2 * M_PI * $r;
    $pos = 0;
@endphp
<svg viewBox="0 0 200 200" class="size-full -rotate-90" role="img" aria-label="Deliveries by branch">
    @if (! $total)
        <circle cx="100" cy="100" r="{{ $r }}" fill="none" stroke="currentColor" class="text-line" stroke-width="{{ $sw }}" />
    @endif
    @foreach ($segments as $s)
        @php
            $frac = $total ? $s['count'] / $total : 0;
            $len = max($frac * $c - $sw - $gap, 0.01);
            $offset = -($pos + $sw / 2 + $gap / 2);
            $pos += $frac * $c;
        @endphp
        <circle cx="100" cy="100" r="{{ $r }}" fill="none" stroke="{{ $s['color'] }}" stroke-width="{{ $sw }}"
                stroke-linecap="round" stroke-dasharray="{{ round($len, 2) }} {{ round($c, 2) }}"
                stroke-dashoffset="{{ round($offset, 2) }}" />
    @endforeach
</svg>
