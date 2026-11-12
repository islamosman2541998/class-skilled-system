@extends('settings.partials.form-shell')

@section('fields')
    <x-panel :title="__('settings.finance.currency')">
        <div class="form-grid">
            <x-field name="currency_code" :label="__('settings.finance.currency_code')" required>
                <input id="currency_code" name="currency_code" value="{{ old('currency_code', $settings->currency_code) }}" @class(['form-control', 'is-invalid' => $errors->has('currency_code')]) required maxlength="3" dir="ltr">
            </x-field>
            <x-field name="currency_symbol_ar" :label="__('settings.finance.currency_symbol_ar')" required>
                <input id="currency_symbol_ar" name="currency_symbol_ar" value="{{ old('currency_symbol_ar', $settings->currency_symbol_ar) }}" @class(['form-control', 'is-invalid' => $errors->has('currency_symbol_ar')]) required maxlength="10">
            </x-field>
            <x-field name="currency_symbol_en" :label="__('settings.finance.currency_symbol_en')" required>
                <input id="currency_symbol_en" name="currency_symbol_en" value="{{ old('currency_symbol_en', $settings->currency_symbol_en) }}" @class(['form-control', 'is-invalid' => $errors->has('currency_symbol_en')]) required maxlength="10" dir="ltr">
            </x-field>
            <x-field name="decimals" :label="__('settings.finance.decimals')" required>
                <input id="decimals" type="number" name="decimals" value="{{ old('decimals', $settings->decimals) }}" @class(['form-control', 'is-invalid' => $errors->has('decimals')]) required min="0" max="3" step="1" dir="ltr">
            </x-field>
        </div>
    </x-panel>

    <x-panel :title="__('settings.finance.documents')">
        <div class="form-grid">
            <x-field name="vat_rate" :label="__('settings.finance.vat_rate')" :hint="__('settings.finance.vat_rate_hint')" required>
                <input id="vat_rate" type="number" name="vat_rate" value="{{ old('vat_rate', $settings->vat_rate) }}" @class(['form-control', 'is-invalid' => $errors->has('vat_rate')]) required min="0" max="100" step="0.01" dir="ltr">
            </x-field>
            <x-field name="quotation_validity_days" :label="__('settings.finance.quotation_validity_days')" required>
                <input id="quotation_validity_days" type="number" name="quotation_validity_days" value="{{ old('quotation_validity_days', $settings->quotation_validity_days) }}" @class(['form-control', 'is-invalid' => $errors->has('quotation_validity_days')]) required min="1" max="365" dir="ltr">
            </x-field>
            <x-field name="invoice_due_days" :label="__('settings.finance.invoice_due_days')" required>
                <input id="invoice_due_days" type="number" name="invoice_due_days" value="{{ old('invoice_due_days', $settings->invoice_due_days) }}" @class(['form-control', 'is-invalid' => $errors->has('invoice_due_days')]) required min="1" max="365" dir="ltr">
            </x-field>
        </div>
    </x-panel>
@endsection
