<?php

namespace App\Http\Requests;

use App\Enums\DiscountType;
use App\Enums\LookupType;
use App\Rules\VisibleClient;
use App\Services\QuotationCalculator;
use App\Settings\FinanceSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use InvalidArgumentException;

class SaveQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('quotation') ? 'quotations.update' : 'quotations.create');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'items' => array_values((array) $this->input('items', [])),
            'payments' => array_values((array) $this->input('payments', [])),
            'discount_type' => $this->input('discount_type') ?: null,
            'discount_value' => $this->input('discount_value') ?: '0',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lookup = fn (LookupType $type) => Rule::exists('lookups', 'id')->where('type', $type->value);

        return [
            'lead_id' => ['nullable', 'integer', Rule::exists('leads', 'id')->whereNull('deleted_at')],
            'client_id' => [Rule::requiredIf(! $this->filled('lead_id')), 'nullable', 'integer', new VisibleClient],
            'service_id' => ['nullable', $lookup(LookupType::Service)],
            'title_ar' => ['required', 'string', 'max:190'],
            'title_en' => ['nullable', 'string', 'max:190'],
            'location' => ['nullable', 'string', 'max:190'],
            'emirate_id' => ['nullable', $lookup(LookupType::Emirate)],
            'issue_date' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after_or_equal:issue_date'],
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'discount_type' => ['nullable', Rule::enum(DiscountType::class)],
            'discount_value' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'terms_ar' => ['nullable', 'string', 'max:10000'],
            'terms_en' => ['nullable', 'string', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.section_ar' => ['nullable', 'string', 'max:190'],
            'items.*.section_en' => ['nullable', 'string', 'max:190'],
            'items.*.price_item_id' => ['nullable', 'integer', Rule::exists('price_items', 'id')],
            'items.*.description_ar' => ['required', 'string', 'max:2000'],
            'items.*.description_en' => ['nullable', 'string', 'max:2000'],
            'items.*.unit_id' => ['nullable', $lookup(LookupType::Unit)],
            'items.*.quantity' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'payments' => ['required', 'array', 'min:1', 'max:20'],
            'payments.*.label_ar' => ['required', 'string', 'max:190'],
            'payments.*.label_en' => ['nullable', 'string', 'max:190'],
            'payments.*.percent' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    /** Payment percentages add up to 100; the discount fits the subtotal (checked by the calculator). */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sum = array_reduce((array) $this->input('payments'), fn (string $carry, array $row) => bcadd($carry, (string) $row['percent'], 2), '0');

            if (bccomp($sum, '100', 2) !== 0) {
                $validator->errors()->add('payments', __('quotations.payments_not_100', ['sum' => $sum]));
            }

            try {
                app(QuotationCalculator::class)->calculate(
                    array_map(fn (array $item) => ['quantity' => (string) $item['quantity'], 'unit_price' => (string) $item['unit_price']], (array) $this->input('items')),
                    DiscountType::tryFrom((string) $this->input('discount_type')),
                    (string) $this->input('discount_value'),
                    '0',
                    app(FinanceSettings::class)->decimals,
                );
            } catch (InvalidArgumentException) {
                $validator->errors()->add('discount_value', __('quotations.discount_invalid'));
            }
        }];
    }
}
