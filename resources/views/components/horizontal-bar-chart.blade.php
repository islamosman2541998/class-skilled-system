@props(['items' => [], 'caption' => ''])
@php($max = max(array_column($items, 'value') ?: [0]))

<figure class="horizontal-chart" role="group" aria-label="{{ $caption }}">
    @foreach ($items as $item)
        @php($width = $max > 0 ? round($item['value'] / $max * 100, 2) : 0)
        <div class="horizontal-chart-row">
            <div class="horizontal-chart-meta">
                <span>{{ $item['label'] }}</span>
                <strong><bdi dir="ltr">{{ number_format($item['value']) }}</bdi></strong>
            </div>
            <div class="horizontal-chart-track" aria-hidden="true">
                <span style="inline-size: {{ $width }}%; background: {{ $item['color'] }};"></span>
            </div>
        </div>
    @endforeach
</figure>
