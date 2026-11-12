@extends('settings.partials.form-shell')

@php
    $list = fn (string $key) => is_array(old($key, $settings->{$key})) ? implode(', ', old($key, $settings->{$key})) : old($key);
    $kinds = ['image' => 'settings.uploads.images', 'video' => 'settings.uploads.videos', 'file' => 'settings.uploads.files'];
@endphp

@section('fields')
    @foreach ($kinds as $kind => $title)
        <x-panel :title="__($title)">
            <div class="form-grid">
                <x-field :name="$kind.'_max_mb'" :label="__('settings.uploads.max_mb')" required>
                    <input id="{{ $kind }}_max_mb" type="number" name="{{ $kind }}_max_mb" value="{{ old($kind.'_max_mb', $settings->{$kind.'_max_mb'}) }}" @class(['form-control', 'is-invalid' => $errors->has($kind.'_max_mb')]) required min="1" max="1024" dir="ltr">
                </x-field>
                <x-field :name="$kind.'_types'" :label="__('settings.uploads.types')" :hint="__('settings.uploads.types_hint')" required>
                    <input id="{{ $kind }}_types" name="{{ $kind }}_types" value="{{ $list($kind.'_types') }}" @class(['form-control', 'is-invalid' => $errors->has($kind.'_types') || $errors->has($kind.'_types.*')]) required dir="ltr">
                    @error($kind.'_types.*')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </x-field>
                @if ($kind === 'image')
                    <x-field name="image_max_dimension" :label="__('settings.uploads.image_max_dimension')" :hint="__('settings.uploads.image_max_dimension_hint')" required>
                        <input id="image_max_dimension" type="number" name="image_max_dimension" value="{{ old('image_max_dimension', $settings->image_max_dimension) }}" @class(['form-control', 'is-invalid' => $errors->has('image_max_dimension')]) required min="500" max="8000" dir="ltr">
                    </x-field>
                @endif
            </div>
        </x-panel>
    @endforeach

    <x-panel :title="__('settings.uploads.transfer')">
        <div class="form-grid">
            <x-field name="chunk_size_mb" :label="__('settings.uploads.chunk_size_mb')" :hint="__('settings.uploads.chunk_size_mb_hint')" required>
                <input id="chunk_size_mb" type="number" name="chunk_size_mb" value="{{ old('chunk_size_mb', $settings->chunk_size_mb) }}" @class(['form-control', 'is-invalid' => $errors->has('chunk_size_mb')]) required min="1" max="10" dir="ltr">
            </x-field>
        </div>
    </x-panel>
@endsection
