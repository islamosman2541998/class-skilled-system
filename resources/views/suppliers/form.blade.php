@extends('layouts.app')

@php
    $editing = $supplier->exists;
    $title = $editing ? __('resources.suppliers.edit') : __('resources.suppliers.new');
    $value = fn (string $key) => old($key, $supplier->{$key});
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('resources.suppliers.suppliers') => route('suppliers.index')] + ($editing ? [$supplier->name => route('suppliers.show', $supplier)] : [])" />

    <form method="POST" action="{{ $editing ? route('suppliers.update', $supplier) : route('suppliers.store') }}" data-dirty-check novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('resources.suppliers.identity')">
            <div class="form-grid">
                <x-field name="type" :label="__('resources.suppliers.type')" required>
                    <select id="type" name="type" class="form-select" required>
                        @foreach (\App\Enums\SupplierType::cases() as $type)
                            <option value="{{ $type->value }}" @selected(old('type', $supplier->type?->value) === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="specialty_id" :label="__('resources.suppliers.specialty')">
                    <select id="specialty_id" name="specialty_id" class="form-select">
                        <option value=""></option>
                        @foreach ($specialties as $specialty)
                            <option value="{{ $specialty->id }}" @selected((int) $value('specialty_id') === $specialty->id)>{{ $specialty->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="name_ar" :label="__('resources.suppliers.name_ar')" required>
                    <input id="name_ar" name="name_ar" value="{{ $value('name_ar') }}" @class(['form-control', 'is-invalid' => $errors->has('name_ar')]) maxlength="190" required dir="rtl">
                </x-field>
                <x-field name="name_en" :label="__('resources.suppliers.name_en')">
                    <input id="name_en" name="name_en" value="{{ $value('name_en') }}" @class(['form-control', 'is-invalid' => $errors->has('name_en')]) maxlength="190" dir="ltr">
                </x-field>
                <x-field name="phone" :label="__('app.phone')">
                    <input id="phone" name="phone" value="{{ $value('phone') }}" @class(['form-control', 'is-invalid' => $errors->has('phone')]) dir="ltr" inputmode="tel">
                </x-field>
                <x-field name="email" :label="__('app.email')">
                    <input id="email" type="email" name="email" value="{{ $value('email') }}" @class(['form-control', 'is-invalid' => $errors->has('email')]) dir="ltr">
                </x-field>
                <x-field name="trn" :label="__('resources.suppliers.trn')" :hint="__('resources.suppliers.trn_hint')">
                    <input id="trn" name="trn" value="{{ $value('trn') }}" @class(['form-control', 'is-invalid' => $errors->has('trn')]) dir="ltr" inputmode="numeric" maxlength="15">
                </x-field>
                <x-field name="address" :label="__('resources.suppliers.address')">
                    <input id="address" name="address" value="{{ $value('address') }}" @class(['form-control', 'is-invalid' => $errors->has('address')]) maxlength="255">
                </x-field>
            </div>
            <div class="form-check mt-3">
                <input type="hidden" name="is_active" value="0">
                <input id="is_active" type="checkbox" name="is_active" value="1" class="form-check-input" @checked((bool) old('is_active', $supplier->is_active ?? true))>
                <label for="is_active" class="form-check-label">{{ __('resources.suppliers.is_active') }}</label>
            </div>
        </x-panel>

        <x-panel :title="__('resources.suppliers.bank')">
            <div class="form-grid">
                <x-field name="bank_name" :label="__('resources.suppliers.bank_name')">
                    <input id="bank_name" name="bank_name" value="{{ $value('bank_name') }}" @class(['form-control', 'is-invalid' => $errors->has('bank_name')]) maxlength="190">
                </x-field>
                <x-field name="bank_account_name" :label="__('resources.suppliers.bank_account_name')">
                    <input id="bank_account_name" name="bank_account_name" value="{{ $value('bank_account_name') }}" @class(['form-control', 'is-invalid' => $errors->has('bank_account_name')]) maxlength="190">
                </x-field>
                <x-field name="bank_iban" :label="__('resources.suppliers.bank_iban')">
                    <input id="bank_iban" name="bank_iban" value="{{ $value('bank_iban') }}" @class(['form-control', 'is-invalid' => $errors->has('bank_iban')]) maxlength="40" dir="ltr">
                </x-field>
            </div>
            <x-field name="notes" :label="__('resources.suppliers.notes')" class="mt-3">
                <textarea id="notes" name="notes" rows="2" class="form-control" maxlength="2000">{{ $value('notes') }}</textarea>
            </x-field>
        </x-panel>

        <x-sticky-actions>
            <a href="{{ $editing ? route('suppliers.show', $supplier) : route('suppliers.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
        </x-sticky-actions>
    </form>
@endsection
