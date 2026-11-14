<?php

namespace App\Http\Requests;

use App\Enums\FollowUpType;
use App\Enums\LookupType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation of the small actions on a lead page; the rules follow the route name.
 * Authorization: leads.update (visibility is checked by the controller).
 */
class LeadActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('leads.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'leads.follow-ups.store' => [
                'type' => ['required', Rule::enum(FollowUpType::class)],
                'body' => ['required', 'string', 'max:5000'],
                'happened_at' => ['nullable', 'date', 'before_or_equal:now'],
            ],
            'leads.assign' => [
                'assigned_to' => ['nullable', 'integer', Rule::exists('users', 'id')->where('is_active', true)->whereNull('deleted_at')],
            ],
            'leads.schedule' => [
                'follow_up_at' => ['nullable', 'date'],
            ],
            'leads.close' => [
                'rejection_reason_id' => ['required', Rule::exists('lookups', 'id')->where('type', LookupType::RejectionReason->value)],
                'rejection_note' => ['nullable', 'string', 'max:2000'],
            ],
            default => [],
        };
    }
}
