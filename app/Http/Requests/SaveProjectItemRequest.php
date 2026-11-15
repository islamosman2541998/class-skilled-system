<?php

namespace App\Http\Requests;

use App\Enums\LookupType;
use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A contract item of a project billed by claims (spec §5.9). Editable until a claim is submitted.
 */
class SaveProjectItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');
        abort_unless($project->isVisibleTo($this->user()) && $project->isClaimsBilled(), 404);

        return $this->user()->can('projects.update') && ! $project->hasLockedClaims();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'section_ar' => ['nullable', 'string', 'max:190'],
            'section_en' => ['nullable', 'string', 'max:190'],
            'description_ar' => ['required', 'string', 'max:2000'],
            'description_en' => ['nullable', 'string', 'max:2000'],
            'unit_id' => ['nullable', 'integer', Rule::exists('lookups', 'id')->where('type', LookupType::Unit->value)],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }
}
