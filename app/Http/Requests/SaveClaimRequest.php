<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A draft claim: the period and, per line, the current quantity or the cumulative percent
 * (spec §8.14). The bound against the contract quantity is checked in SaveClaim.
 */
class SaveClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('claims.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'period_from' => ['required', 'date_format:Y-m-d'],
            'period_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_from'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['nullable', 'array'],
            'lines.*.current_quantity' => ['nullable', 'numeric', 'min:-999999999', 'max:999999999'],
            'lines.*.cumulative_percent' => ['nullable', 'numeric', 'min:-999999', 'max:999999'],
        ];
    }
}
