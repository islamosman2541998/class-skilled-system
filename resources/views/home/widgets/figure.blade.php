{{-- One dashboard figure; it links to the filtered list that produced it (spec §9.1). --}}
@if ($item['url'])
    <a href="{{ $item['url'] }}" class="dash-figure">
        <span class="dash-figure-label">{{ $item['label'] }}</span>
        <span class="dash-figure-value"><bdi dir="ltr">{{ $item['value'] }}</bdi></span>
    </a>
@else
    <div class="dash-figure">
        <span class="dash-figure-label">{{ $item['label'] }}</span>
        <span class="dash-figure-value"><bdi dir="ltr">{{ $item['value'] }}</bdi></span>
    </div>
@endif
