{{-- Save bar fixed to the bottom of the viewport for long forms (spec §5.3). --}}
<div {{ $attributes->class(['sticky-actions']) }}>
    {{ $slot }}
</div>
