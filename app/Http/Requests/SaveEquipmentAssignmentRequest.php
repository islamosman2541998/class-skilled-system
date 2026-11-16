<?php

namespace App\Http\Requests;

use App\Models\Project;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/** Equipment on a project for a period (spec §8.17). Only projects the user sees. */
class SaveEquipmentAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('equipment.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                if (! Project::query()->visibleTo($this->user())->whereKey($value)->exists()) {
                    $fail(__('validation.exists', ['attribute' => __('resources.equipment.project')]));
                }
            }],
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'daily_cost' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
