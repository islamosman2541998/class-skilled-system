<?php

namespace App\Http\Requests;

use App\Enums\EquipmentOwnership;
use App\Enums\LookupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A piece of equipment (spec §8.17). */
class SaveEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('equipment') ? 'equipment.update' : 'equipment.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'type_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', LookupType::EquipmentType->value)],
            'ownership' => ['required', Rule::enum(EquipmentOwnership::class)],
            'daily_cost' => ['required', 'numeric', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
