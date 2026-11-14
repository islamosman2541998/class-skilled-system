<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveVariationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('projects.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['items' => array_values((array) $this->input('items', []))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.description_ar' => ['required', 'string', 'max:2000'],
            'items.*.description_en' => ['nullable', 'string', 'max:2000'],
            'items.*.unit_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', LookupType::Unit->value)],
            // Negative quantities are removed work.
            'items.*.quantity' => ['required', 'numeric', 'not_in:0', 'min:-999999999', 'max:999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }
}
