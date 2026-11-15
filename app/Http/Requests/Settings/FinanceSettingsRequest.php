<?php

namespace App\Http\Requests\Settings;

class FinanceSettingsRequest extends SettingsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'currency_code' => ['required', 'alpha', 'size:3'],
            'currency_symbol_ar' => ['required', 'string', 'max:10'],
            'currency_symbol_en' => ['required', 'string', 'max:10'],
            'decimals' => ['required', 'integer', 'between:0,3'],
            'vat_rate' => ['required', 'numeric', 'between:0,100'],
            'quotation_validity_days' => ['required', 'integer', 'between:1,365'],
            'invoice_due_days' => ['required', 'integer', 'between:1,365'],
            'retention_percent' => ['required', 'numeric', 'between:0,100'],
        ];
    }

    public function values(): array
    {
        $values = $this->validated();

        return [
            ...$values,
            'currency_code' => strtoupper($values['currency_code']),
            'decimals' => (int) $values['decimals'],
            'vat_rate' => number_format((float) $values['vat_rate'], 2, '.', ''),
            'quotation_validity_days' => (int) $values['quotation_validity_days'],
            'invoice_due_days' => (int) $values['invoice_due_days'],
            'retention_percent' => number_format((float) $values['retention_percent'], 2, '.', ''),
        ];
    }
}
