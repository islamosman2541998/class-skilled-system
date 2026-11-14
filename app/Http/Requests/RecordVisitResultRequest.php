<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordVisitResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('site_visits.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['done', 'cancelled'])],
            'notes' => ['nullable', 'string', 'max:10000'],
            'measurements' => ['nullable', 'string', 'max:10000'],
            'uploads' => ['nullable', 'array', 'max:50'],
            'uploads.*' => ['string', 'uuid'],
        ];
    }
}
