<?php

namespace App\Http\Requests\Settings;

class CompanySettingsRequest extends SettingsRequest
{
    /** Branding images: form field => settings property. */
    public const IMAGES = [
        'logo' => 'logo_path',
        'login_logo' => 'login_logo_path',
        'stamp' => 'stamp_path',
        'signature' => 'signature_path',
    ];

    protected function prepareForValidation(): void
    {
        $phones = $this->input('phones');

        $this->merge([
            'phones' => array_values(array_filter(array_map('trim', is_array($phones) ? $phones : (preg_split('/\R/u', (string) $phones) ?: [])))),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $image = ['nullable', 'file', 'mimes:png,jpg,jpeg,webp', 'max:2048'];

        return [
            'name_ar' => ['required', 'string', 'max:150'],
            'name_en' => ['required', 'string', 'max:150'],
            'trn' => ['nullable', 'regex:/^\d{15}$/'],
            'license_no' => ['nullable', 'string', 'max:60'],
            'address_ar' => ['nullable', 'string', 'max:255'],
            'address_en' => ['nullable', 'string', 'max:255'],
            'phones' => ['array', 'max:5'],
            'phones.*' => ['string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'string', 'max:150'],
            'bank_iban' => ['nullable', 'string', 'max:34'],
            'logo' => $image,
            'login_logo' => $image,
            'stamp' => $image,
            'signature' => $image,
            'remove_logo' => ['nullable', 'boolean'],
            'remove_login_logo' => ['nullable', 'boolean'],
            'remove_stamp' => ['nullable', 'boolean'],
            'remove_signature' => ['nullable', 'boolean'],
        ];
    }

    public function values(): array
    {
        $text = ['name_ar', 'name_en', 'trn', 'license_no', 'address_ar', 'address_en', 'email', 'bank_name', 'bank_account_name', 'bank_iban'];
        $values = ['phones' => $this->validated('phones', [])];

        foreach ($text as $key) {
            $values[$key] = $this->validated($key);
        }

        return $values;
    }
}
