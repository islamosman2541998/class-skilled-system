<?php

namespace App\Http\Requests;

use App\Enums\BillingMethod;
use App\Enums\LookupType;
use App\Enums\ProjectStatus;
use App\Rules\VisibleClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('project') ? 'projects.update' : 'projects.create');
    }

    /** A form with every team member unselected sends no "members" field; it still means "no team". */
    protected function prepareForValidation(): void
    {
        if ($this->has('members_present') && ! $this->has('members')) {
            $this->merge(['members' => []]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lookup = fn (LookupType $type) => Rule::exists('lookups', 'id')->where('type', $type->value);
        $activeUser = Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at');
        $editing = $this->route('project') !== null;

        return [
            'client_id' => [$editing ? 'nullable' : 'required', 'integer', new VisibleClient],
            'title_ar' => ['required', 'string', 'max:190'],
            'title_en' => ['nullable', 'string', 'max:190'],
            'service_id' => ['nullable', $lookup(LookupType::Service)],
            'emirate_id' => ['nullable', $lookup(LookupType::Emirate)],
            'location' => ['nullable', 'string', 'max:190'],
            'original_value' => [$editing ? 'nullable' : 'required', 'numeric', 'min:0', 'max:999999999999'],
            'billing_method' => ['required', Rule::enum(BillingMethod::class)],
            'start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'actual_end_date' => ['nullable', 'date'],
            'manager_id' => ['required', 'integer', $activeUser],
            'members' => ['nullable', 'array', 'max:50'],
            'members.*' => ['integer', $activeUser],
            'status' => [$editing ? 'required' : 'nullable', Rule::enum(ProjectStatus::class)],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
