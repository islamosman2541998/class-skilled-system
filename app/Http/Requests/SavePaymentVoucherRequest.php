<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A payment voucher and, on create, its allocations (spec §8.16). Allocation limits are checked in
 * AllocatePaymentVoucher under row locks.
 */
class SavePaymentVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('payment_voucher') ? 'payment_vouchers.update' : 'payment_vouchers.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('payment_voucher') === null;

        return [
            'supplier_id' => [$creating ? 'required' : 'nullable', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'voucher_date' => ['required', 'date_format:Y-m-d'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'payment_method_id' => ['required', 'integer', Rule::exists('lookups', 'id')->where('type', LookupType::PaymentMethod->value)],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'allocations' => ['nullable', 'array', 'max:200'],
            'allocations.*' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }
}
