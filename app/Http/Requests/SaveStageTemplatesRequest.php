<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveStageTemplatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['stages' => array_values((array) $this->input('stages', []))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stages' => ['present', 'array', 'max:50'],
            'stages.*.name_ar' => ['required', 'string', 'max:190'],
            'stages.*.name_en' => ['nullable', 'string', 'max:190'],
            'stages.*.weight' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }
}
