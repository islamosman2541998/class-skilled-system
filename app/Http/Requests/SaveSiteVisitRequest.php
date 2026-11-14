<?php

namespace App\Http\Requests;

use App\Enums\SiteVisitStatus;
use App\Rules\VisibleClient;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSiteVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('site_visit') ? 'site_visits.update' : 'site_visits.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lead_id' => ['nullable', 'integer', Rule::exists('leads', 'id')->whereNull('deleted_at')],
            'client_id' => [Rule::requiredIf(! $this->filled('lead_id')), 'nullable', 'integer', new VisibleClient],
            'scheduled_at' => ['required', 'date'],
            'engineer_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'address' => ['nullable', 'string', 'max:1000'],
            'map_url' => ['nullable', 'url:https', 'max:500'],
            'status' => [$this->route('site_visit') ? 'required' : 'nullable', Rule::enum(SiteVisitStatus::class)],
            'notes' => ['nullable', 'string', 'max:10000'],
            'measurements' => ['nullable', 'string', 'max:10000'],
        ];
    }
}
