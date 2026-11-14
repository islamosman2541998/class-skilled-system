<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RejectQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotations.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rejection_reason_id' => ['required', Rule::exists('lookups', 'id')->where('type', LookupType::RejectionReason->value)],
            'rejection_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
