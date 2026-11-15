<?php

namespace App\Http\Requests;

use App\Models\Project;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Retention, contract discount and advance of a project billed by claims (spec §5.9).
 * Editable until a claim is submitted.
 */
class SaveClaimSetupRequest extends FormRequest
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
            'retention_percent' => ['required', 'numeric', 'between:0,100'],
            'contract_discount_percent' => ['required', 'numeric', 'between:0,100'],
            'advance_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
        ];
    }
}
