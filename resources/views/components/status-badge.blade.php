@props(['color' => 'neutral'])
{{-- color: success | warning | danger | info | neutral --}}
<span {{ $attributes->class(['status', 'status-'.$color]) }}>{{ $slot }}</span>
