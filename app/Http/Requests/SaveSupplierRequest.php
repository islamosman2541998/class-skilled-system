<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use App\Enums\SupplierType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A supplier or subcontractor (spec §8.15). The IBAN is stored without spaces, upper case.
 */
class SaveSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('supplier') ? 'suppliers.update' : 'suppliers.create');
    }

    protected function prepareForValidation(): void
    {
        if (filled($this->input('bank_iban'))) {
            $this->merge(['bank_iban' => strtoupper((string) preg_replace('/\s+/', '', (string) $this->input('bank_iban')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(SupplierType::class)],
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'specialty_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', LookupType::SupplierSpecialty->value)],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'trn' => ['nullable', 'digits:15'],
            'address' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:190'],
            'bank_account_name' => ['nullable', 'string', 'max:190'],
            'bank_iban' => ['nullable', 'string', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
