<?php

namespace App\Http\Requests;

use App\Enums\BillingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** The fields confirmed when an accepted quotation becomes a project. */
class ConvertQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('projects.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $activeUser = Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at');

        return [
            'billing_method' => ['required', Rule::enum(BillingMethod::class)],
            'start_date' => ['nullable', 'date'],
            'planned_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'manager_id' => ['required', 'integer', $activeUser],
            'members' => ['nullable', 'array', 'max:50'],
            'members.*' => ['integer', $activeUser],
            'description' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
