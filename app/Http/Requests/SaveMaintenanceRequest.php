<?php

namespace App\Http\Requests;

use App\Enums\MaintenanceStatus;
use App\Models\MaintenanceRequest;
use App\Models\Project;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->editing() ? 'maintenance.update' : 'maintenance.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $editing = $this->editing();

        return [
            // Only projects the user sees, so an assigned user cannot attach a request to a hidden project.
            'project_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                if (! Project::query()->visibleTo($this->user())->whereKey($value)->exists()) {
                    $fail(__('validation.exists', ['attribute' => __('maintenance.project')]));
                }
            }],
            'request_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:10000'],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'status' => [$editing ? 'required' : 'nullable', Rule::enum(MaintenanceStatus::class)],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'in_warranty' => [$editing ? 'required' : 'nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /**
     * @return array<int, Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (! $this->editing() || $validator->errors()->hasAny(['project_id', 'request_date', 'in_warranty'])) {
                return;
            }

            // The warranty flag follows the handover; setting it against the handover needs a reason.
            if ($this->boolean('in_warranty') !== $this->computedWarranty() && blank($this->input('notes'))) {
                $validator->errors()->add('notes', __('maintenance.override_reason'));
            }
        }];
    }

    /** Whether the handover warranty of the chosen project covers the request date. */
    public function computedWarranty(): bool
    {
        $project = Project::query()->with('handover')->findOrFail($this->integer('project_id'));

        return MaintenanceRequest::warrantyCovers($project, Carbon::parse((string) $this->input('request_date')));
    }

    private function editing(): bool
    {
        return $this->route('maintenance') !== null;
    }
}
