<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use App\Models\Project;
use App\Services\Finance\ExpenseAmounts;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('expense') ? 'expenses.update' : 'expenses.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...self::projectRules($this),
            'category_id' => ['required', Rule::exists('lookups', 'id')->where('type', LookupType::ExpenseCategory->value)],
            'supplier_name' => ['nullable', 'string', 'max:190'],
            'expense_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'vat_mode' => ['required', Rule::in(ExpenseAmounts::MODES)],
            'payment_method_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', LookupType::PaymentMethod->value)],
            'paid_by' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'uploads' => ['nullable', 'array', 'max:20'],
            'uploads.*' => ['string', 'uuid'],
        ];
    }

    /**
     * A project the user sees; a general expense (no project) only for users who see all records.
     *
     * @return array<string, mixed>
     */
    public static function projectRules(FormRequest $request): array
    {
        $user = $request->user();

        return [
            'project_id' => [$user->seesAllRecords() ? 'nullable' : 'required', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($user) {
                if (! Project::query()->visibleTo($user)->whereKey($value)->exists()) {
                    $fail(__('validation.exists', ['attribute' => __('finance.project')]));
                }
            }],
        ];
    }
}
