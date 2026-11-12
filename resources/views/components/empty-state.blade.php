@props(['message', 'icon' => 'inbox'])
<div {{ $attributes->class(['empty-state']) }}>
    <x-icon :name="$icon" :size="28" />
    <p>{{ $message }}</p>
    {{ $slot }}
</div>
