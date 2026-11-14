<?php

namespace App\Http\Requests;

use App\Enums\ClientType;
use App\Enums\LookupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('client') ? 'clients.update' : 'clients.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lookup = fn (LookupType $type) => Rule::exists('lookups', 'id')->where('type', $type->value);

        return [
            'type' => ['required', Rule::enum(ClientType::class)],
            'name_ar' => ['required', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+\-\s()]{7,40}$/'],
            'phone2' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+\-\s()]{7,40}$/'],
            'whatsapp' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+\-\s()]{7,40}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'trn' => ['nullable', 'digits:15'],
            'emirate_id' => ['nullable', $lookup(LookupType::Emirate)],
            'area_id' => ['nullable', $lookup(LookupType::Area)],
            'address' => ['nullable', 'string', 'max:1000'],
            'source_id' => ['nullable', $lookup(LookupType::LeadSource)],
            'notes' => ['nullable', 'string', 'max:5000'],
            'contacts' => ['nullable', 'array', 'max:30'],
            'contacts.*.name' => ['required', 'string', 'max:190'],
            'contacts.*.position' => ['nullable', 'string', 'max:190'],
            'contacts.*.phone' => ['nullable', 'string', 'max:40'],
            'contacts.*.email' => ['nullable', 'email', 'max:190'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function clientData(): array
    {
        return $this->safe()->except('contacts');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contacts(): array
    {
        return array_values($this->validated('contacts', []));
    }
}
