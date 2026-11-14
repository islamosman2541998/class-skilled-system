<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The phone quick entry (spec §8.12): receipt photo, amount, category, project.
 */
class QuickExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('expenses.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...SaveExpenseRequest::projectRules($this),
            'category_id' => ['required', Rule::exists('lookups', 'id')->where('type', LookupType::ExpenseCategory->value)],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'includes_vat' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:2000'],
            'uploads' => ['nullable', 'array', 'max:10'],
            'uploads.*' => ['string', 'uuid'],
        ];
    }
}
