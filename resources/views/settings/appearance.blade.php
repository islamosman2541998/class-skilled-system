@extends('settings.partials.form-shell')

@php
    $colors = \App\Http\Requests\Settings\AppearanceSettingsRequest::COLORS;
@endphp

@section('fields')
    <x-panel :title="__('settings.appearance.colors')">
        <div class="form-grid">
            @foreach ($colors as $color)
                <x-field :name="$color" :label="__('settings.appearance.'.$color)" required>
                    <div class="color-input">
                        <input type="color" value="{{ old($color, $settings->{$color}) }}" aria-label="{{ __('settings.appearance.'.$color) }}" data-color-for="{{ $color }}">
                        <input id="{{ $color }}" name="{{ $color }}" value="{{ old($color, $settings->{$color}) }}" @class(['form-control', 'is-invalid' => $errors->has($color)]) required maxlength="7" dir="ltr" pattern="#[0-9A-Fa-f]{6}">
                    </div>
                </x-field>
            @endforeach
        </div>
    </x-panel>

    <x-panel :title="__('settings.appearance.fonts')">
        <div class="form-grid">
            <x-field name="primary_font" :label="__('settings.appearance.primary_font')" :hint="__('settings.appearance.primary_font_hint')" required>
                <select id="primary_font" name="primary_font" @class(['form-select', 'is-invalid' => $errors->has('primary_font')])>
                    <option value="cairo" @selected(old('primary_font', $settings->primary_font) === 'cairo')>{{ __('settings.appearance.cairo') }}</option>
                    <option value="tajawal" @selected(old('primary_font', $settings->primary_font) === 'tajawal')>{{ __('settings.appearance.tajawal') }}</option>
                </select>
            </x-field>

            <x-field name="heading_font" :label="__('settings.appearance.heading_font')" required>
                <select id="heading_font" name="heading_font" class="form-select">
                    <option value="same" @selected(old('heading_font', $settings->heading_font) === 'same')>{{ __('settings.appearance.heading_same') }}</option>
                    <option value="other" @selected(old('heading_font', $settings->heading_font) === 'other')>{{ __('settings.appearance.heading_other') }}</option>
                </select>
            </x-field>

            <x-field name="default_locale" :label="__('settings.appearance.default_locale')" required>
                <select id="default_locale" name="default_locale" class="form-select">
                    <option value="ar" @selected(old('default_locale', $settings->default_locale) === 'ar')>{{ __('app.arabic') }}</option>
                    <option value="en" @selected(old('default_locale', $settings->default_locale) === 'en')>{{ __('app.english') }}</option>
                </select>
            </x-field>
        </div>
    </x-panel>
@endsection

@push('scripts')
    <script type="module">
        // Keep the colour picker and its hex text field in step.
        document.querySelectorAll('[data-color-for]').forEach((picker) => {
            const text = document.getElementById(picker.dataset.colorFor);

            picker.addEventListener('input', () => { text.value = picker.value.toUpperCase(); text.dispatchEvent(new Event('input', { bubbles: true })); });
            text.addEventListener('input', () => { if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) picker.value = text.value; });
        });
    </script>
@endpush
