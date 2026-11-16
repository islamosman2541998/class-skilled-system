<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A worker (spec §8.17). */
class SaveWorkerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('worker') ? 'workers.update' : 'workers.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'trade_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', LookupType::WorkerTrade->value)],
            'daily_wage' => ['required', 'numeric', 'min:0', 'max:99999'],
            'is_company' => ['nullable', 'boolean'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
