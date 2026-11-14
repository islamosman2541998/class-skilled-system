<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePriceItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('price_item') ? 'price_items.update' : 'price_items.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description_ar' => ['required', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'service_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', LookupType::Service->value)],
            'unit_id' => ['required', Rule::exists('lookups', 'id')->where('type', LookupType::Unit->value)],
            'default_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return [...$this->safe()->except('is_active'), 'is_active' => $this->boolean('is_active', true)];
    }
}
