@props(['name', 'label', 'required' => false, 'hint' => null, 'for' => null])
@php
    $errorKey = str_replace(['[]', '[', ']'], ['', '.', ''], $name);
@endphp
<div {{ $attributes->class(['field']) }}>
    <label class="form-label" for="{{ $for ?? $name }}">
        {{ $label }}
        @if ($required)
            <span class="req" aria-hidden="true">*</span>
        @endif
    </label>
    {{ $slot }}
    @if ($hint)
        <div class="form-text">{{ $hint }}</div>
    @endif
    <div class="invalid-feedback d-block" data-error-for="{{ $errorKey }}" role="alert">@error($errorKey){{ $message }}@enderror</div>
</div>
