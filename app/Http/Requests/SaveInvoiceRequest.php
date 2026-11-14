<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use App\Models\Project;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('invoice') ? 'invoices.update' : 'invoices.create');
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
            // Only projects the user sees, so an assigned user cannot invoice a hidden project.
            'project_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                if (! Project::query()->visibleTo($this->user())->whereKey($value)->exists()) {
                    $fail(__('validation.exists', ['attribute' => __('finance.project')]));
                }
            }],
            'issue_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'terms_ar' => ['nullable', 'string', 'max:10000'],
            'terms_en' => ['nullable', 'string', 'max:10000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.description_ar' => ['required', 'string', 'max:2000'],
            'items.*.description_en' => ['nullable', 'string', 'max:2000'],
            'items.*.unit_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', LookupType::Unit->value)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }
}
