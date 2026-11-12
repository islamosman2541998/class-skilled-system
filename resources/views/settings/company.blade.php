@extends('settings.partials.form-shell')

@php
    $text = fn (string $key) => old($key, $settings->{$key});
    $images = [
        'logo' => ['settings.company.logo', 'logo_path'],
        'login_logo' => ['settings.company.login_logo', 'login_logo_path'],
        'stamp' => ['settings.company.stamp', 'stamp_path'],
        'signature' => ['settings.company.signature', 'signature_path'],
    ];
@endphp

@section('fields')
    <x-panel :title="__('settings.company.identity')">
        <div class="form-grid">
            <x-field name="name_ar" :label="__('settings.name_ar')" required>
                <input id="name_ar" name="name_ar" value="{{ $text('name_ar') }}" @class(['form-control', 'is-invalid' => $errors->has('name_ar')]) required maxlength="150" dir="rtl">
            </x-field>
            <x-field name="name_en" :label="__('settings.name_en')" required>
                <input id="name_en" name="name_en" value="{{ $text('name_en') }}" @class(['form-control', 'is-invalid' => $errors->has('name_en')]) required maxlength="150" dir="ltr">
            </x-field>
            <x-field name="trn" :label="__('settings.company.trn')" :hint="__('settings.company.trn_hint')">
                <input id="trn" name="trn" value="{{ $text('trn') }}" @class(['form-control', 'is-invalid' => $errors->has('trn')]) maxlength="15" inputmode="numeric" dir="ltr">
            </x-field>
            <x-field name="license_no" :label="__('settings.company.license_no')">
                <input id="license_no" name="license_no" value="{{ $text('license_no') }}" class="form-control" maxlength="60" dir="ltr">
            </x-field>
        </div>
    </x-panel>

    <x-panel :title="__('settings.company.contact')">
        <div class="form-grid">
            <x-field name="address_ar" :label="__('settings.company.address_ar')">
                <input id="address_ar" name="address_ar" value="{{ $text('address_ar') }}" class="form-control" maxlength="255" dir="rtl">
            </x-field>
            <x-field name="address_en" :label="__('settings.company.address_en')">
                <input id="address_en" name="address_en" value="{{ $text('address_en') }}" class="form-control" maxlength="255" dir="ltr">
            </x-field>
            <x-field name="phones" :label="__('settings.company.phones')" :hint="__('settings.company.phones_hint')">
                <textarea id="phones" name="phones" rows="3" @class(['form-control', 'is-invalid' => $errors->has('phones') || $errors->has('phones.*')]) dir="ltr">{{ is_array(old('phones', $settings->phones)) ? implode("\n", old('phones', $settings->phones)) : old('phones') }}</textarea>
            </x-field>
            <x-field name="email" :label="__('app.email')">
                <input id="email" type="email" name="email" value="{{ $text('email') }}" @class(['form-control', 'is-invalid' => $errors->has('email')]) maxlength="190" dir="ltr">
            </x-field>
        </div>
    </x-panel>

    <x-panel :title="__('settings.company.bank')">
        <div class="form-grid">
            <x-field name="bank_name" :label="__('settings.company.bank_name')">
                <input id="bank_name" name="bank_name" value="{{ $text('bank_name') }}" class="form-control" maxlength="120">
            </x-field>
            <x-field name="bank_account_name" :label="__('settings.company.bank_account_name')">
                <input id="bank_account_name" name="bank_account_name" value="{{ $text('bank_account_name') }}" class="form-control" maxlength="150">
            </x-field>
            <x-field name="bank_iban" :label="__('settings.company.bank_iban')">
                <input id="bank_iban" name="bank_iban" value="{{ $text('bank_iban') }}" @class(['form-control', 'is-invalid' => $errors->has('bank_iban')]) maxlength="34" dir="ltr">
            </x-field>
        </div>
    </x-panel>

    <x-panel :title="__('settings.company.images')">
        <div class="form-grid">
            @foreach ($images as $field => [$label, $property])
                <x-field :name="$field" :label="__($label)" :hint="__('settings.company.image_hint')">
                    @if ($settings->{$property})
                        <div class="image-current">
                            <img src="{{ Storage::disk('public')->url($settings->{$property}) }}" alt="">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_{{ $field }}" value="1" id="remove_{{ $field }}">
                                <label class="form-check-label" for="remove_{{ $field }}">{{ __('settings.company.remove_image') }}</label>
                            </div>
                        </div>
                    @endif
                    <input id="{{ $field }}" type="file" name="{{ $field }}" accept=".png,.jpg,.jpeg,.webp" @class(['form-control', 'is-invalid' => $errors->has($field)])>
                </x-field>
            @endforeach
        </div>
    </x-panel>
@endsection
