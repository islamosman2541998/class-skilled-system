<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancelling a financial document needs a reason and the module's "cancel" permission
 * (spec §6, §8.10, §8.11).
 */
class CancelDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $module = $this->route('receipt') !== null ? 'receipts' : 'invoices';

        return $this->user()->can($module.'.cancel');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:2000'],
            'replace' => ['nullable', 'boolean'],
        ];
    }
}
