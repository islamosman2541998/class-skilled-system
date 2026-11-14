<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('lead') ? 'leads.update' : 'leads.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $lookup = fn (LookupType $type) => Rule::exists('lookups', 'id')->where('type', $type->value);
        $withoutClient = ! $this->filled('client_id');

        return [
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'contact_name' => [Rule::requiredIf($withoutClient), 'nullable', 'string', 'max:190'],
            'contact_phone' => [Rule::requiredIf($withoutClient), 'nullable', 'string', 'max:40', 'regex:/^[0-9+\-\s()]{7,40}$/'],
            'contact_email' => ['nullable', 'email', 'max:190'],
            'service_id' => ['nullable', $lookup(LookupType::Service)],
            'facility_type' => ['nullable', 'string', 'max:100'],
            'project_state' => ['nullable', 'string', 'max:100'],
            'emirate_id' => ['nullable', $lookup(LookupType::Emirate)],
            'area_id' => ['nullable', $lookup(LookupType::Area)],
            'area_text' => ['nullable', 'string', 'max:190'],
            'area_m2' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'expected_start' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:10000'],
            'source_id' => ['required', $lookup(LookupType::LeadSource)],
            'status_id' => [$this->route('lead') ? 'required' : 'nullable', $lookup(LookupType::LeadStatus)],
            'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'follow_up_at' => ['nullable', 'date'],
        ];
    }
}
