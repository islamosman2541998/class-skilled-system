<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Returning a submitted claim to draft needs a note and claims.approve (spec §8.14).
 */
class ReturnClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('claims.approve');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'return_note' => ['required', 'string', 'max:2000'],
        ];
    }
}
