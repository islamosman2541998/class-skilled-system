<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use App\Models\Lookup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->isMethod('POST') ? 'lookups.create' : 'lookups.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var LookupType $type */
        $type = $this->route('type');
        $lookup = $this->route('lookup');
        $parentType = $type->parentType();

        return [
            'name_ar' => [
                'required', 'string', 'max:120',
                Rule::unique('lookups', 'name_ar')
                    ->where('type', $type->value)
                    ->where('parent_id', $parentType ? $this->input('parent_id') : null)
                    ->whereNull('deleted_at')
                    ->ignore($lookup instanceof Lookup ? $lookup->getKey() : null),
            ],
            'name_en' => ['nullable', 'string', 'max:120'],
            'parent_id' => $parentType
                ? ['required', 'integer', Rule::exists('lookups', 'id')->where('type', $parentType->value)->whereNull('deleted_at')]
                : ['prohibited'],
            'color' => $type->hasColor() ? ['nullable', Rule::in(Lookup::COLORS)] : ['prohibited'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name_ar' => __('settings.name_ar'),
            'name_en' => __('settings.name_en'),
            'parent_id' => __('settings.parent'),
            'color' => __('settings.color'),
        ];
    }
}
