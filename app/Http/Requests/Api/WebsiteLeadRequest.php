<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A quote request sent by the website (fields of its QuoteRequest model, spec §10).
 */
class WebsiteLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // VerifyWebsiteKey guards the route.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return self::payloadRules() + [
            'files' => ['nullable', 'array', 'max:20'],
            'files.*' => ['file', 'max:204800'],
        ];
    }

    /**
     * Rules shared with the "fetch requests" sync, where files arrive as URLs.
     *
     * @return array<string, mixed>
     */
    public static function payloadRules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:190'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'service' => ['nullable', 'string', 'max:190'],
            'facility_type' => ['nullable', 'string', 'max:100'],
            'project_state' => ['nullable', 'string', 'max:100'],
            'emirate' => ['nullable', 'string', 'max:100'],
            'area_text' => ['nullable', 'string', 'max:190'],
            'area_m2' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'start_when' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:10000'],
            'preferred_contact' => ['nullable', 'string', 'max:20'],
            'locale' => ['nullable', 'in:ar,en'],
        ];
    }
}
