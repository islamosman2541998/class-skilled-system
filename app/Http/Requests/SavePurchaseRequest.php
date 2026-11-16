<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use App\Models\Project;
use App\Models\Purchase;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A purchase invoice (spec §8.16). Only projects the user sees; the supplier's invoice number is
 * unique per supplier among live invoices.
 */
class SavePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('purchase') ? 'purchases.update' : 'purchases.create');
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
        /** @var Purchase|null $purchase */
        $purchase = $this->route('purchase');

        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->whereNull('deleted_at')],
            'project_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                if (! Project::query()->visibleTo($this->user())->whereKey($value)->exists()) {
                    $fail(__('validation.exists', ['attribute' => __('resources.purchases.project')]));
                }
            }],
            'purchase_date' => ['required', 'date_format:Y-m-d'],
            'supplier_invoice_no' => ['required', 'string', 'max:60', Rule::unique('purchases', 'supplier_invoice_no')
                ->where('supplier_id', (int) $this->input('supplier_id'))
                ->whereNull('deleted_at')
                ->ignore($purchase?->id)],
            'vat' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.description_ar' => ['required', 'string', 'max:2000'],
            'items.*.description_en' => ['nullable', 'string', 'max:2000'],
            'items.*.unit_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', LookupType::Unit->value)],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'uploads' => ['nullable', 'array', 'max:10'],
            'uploads.*' => ['string', 'uuid'],
        ];
    }
}
