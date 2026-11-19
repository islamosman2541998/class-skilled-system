@props(['items' => [], 'caption' => '', 'centerLabel' => ''])
@php
    $total = array_sum(array_column($items, 'value'));
    $cursor = 0;
    $stops = [];
    foreach ($items as $item) {
        if ($total <= 0 || $item['value'] <= 0) continue;
        $start = $cursor / $total * 100;
        $cursor += $item['value'];
        $end = $cursor / $total * 100;
        $stops[] = "{$item['color']} {$start}% {$end}%";
    }
    $background = $stops === [] ? 'var(--c-line)' : 'conic-gradient('.implode(', ', $stops).')';
@endphp

<figure class="donut-chart" role="group" aria-label="{{ $caption }}">
    <div class="donut-visual" style="background: {{ $background }};" aria-hidden="true">
        <div class="donut-center">
            <strong><bdi dir="ltr">{{ number_format($total) }}</bdi></strong>
            <span>{{ $centerLabel }}</span>
        </div>
    </div>
    <ul class="donut-legend">
        @foreach ($items as $item)
            <li>
                <span class="chart-swatch" style="background: {{ $item['color'] }};"></span>
                <span class="donut-label">{{ $item['label'] }}</span>
                <strong><bdi dir="ltr">{{ number_format($item['value']) }}</bdi></strong>
            </li>
        @endforeach
    </ul>
</figure>
