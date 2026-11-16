<?php

namespace App\Http\Requests;

use App\Enums\SubcontractStatus;
use App\Enums\SupplierType;
use App\Models\Project;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A subcontract and its payment lines (spec §8.16). The supplier must be a subcontractor; the
 * total and billed-line rules are checked in SaveSubcontract.
 */
class SaveSubcontractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('subcontract') ? 'subcontracts.update' : 'subcontracts.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['payments' => array_values((array) $this->input('payments', []))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('type', SupplierType::Subcontractor->value)->whereNull('deleted_at')],
            'project_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                if (! Project::query()->visibleTo($this->user())->whereKey($value)->exists()) {
                    $fail(__('validation.exists', ['attribute' => __('resources.subcontracts.project')]));
                }
            }],
            'scope' => ['required', 'string', 'max:5000'],
            'value' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'vat' => ['nullable', 'boolean'],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['nullable', Rule::enum(SubcontractStatus::class)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'payments' => ['nullable', 'array', 'max:50'],
            'payments.*.id' => ['nullable', 'integer'],
            'payments.*.label_ar' => ['required', 'string', 'max:190'],
            'payments.*.label_en' => ['nullable', 'string', 'max:190'],
            'payments.*.amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'payments.*.due_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
