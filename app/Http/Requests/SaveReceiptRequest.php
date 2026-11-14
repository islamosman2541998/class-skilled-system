<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use App\Models\Project;
use App\Rules\VisibleClient;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('receipt') ? 'receipts.update' : 'receipts.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('receipt') === null;

        return [
            // Client and project are chosen once; an edit keeps them.
            'client_id' => $creating ? ['required', 'integer', new VisibleClient] : ['exclude'],
            'project_id' => $creating ? ['nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                $visible = Project::query()->visibleTo($this->user())->whereKey($value)->where('client_id', $this->integer('client_id'))->exists();

                if (! $visible) {
                    $fail(__('validation.exists', ['attribute' => __('finance.project')]));
                }
            }] : ['exclude'],
            'receipt_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'payment_method_id' => ['required', Rule::exists('lookups', 'id')->where('type', LookupType::PaymentMethod->value)],
            'reference' => ['nullable', 'string', 'max:100'],
            'cheque_date' => ['nullable', 'date'],
            'received_by' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:5000'],
            'allocations' => $creating ? ['nullable', 'array', 'max:200'] : ['exclude'],
            'allocations.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }
}
