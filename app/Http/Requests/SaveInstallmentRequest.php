<?php

namespace App\Http\Requests;

use App\Enums\InstallmentDueType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('projects.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $project = $this->route('project');

        return [
            'label_ar' => ['required', 'string', 'max:190'],
            'label_en' => ['nullable', 'string', 'max:190'],
            'percent' => ['nullable', 'required_without:amount', 'numeric', 'gt:0', 'max:100'],
            'amount' => ['nullable', 'required_without:percent', 'numeric', 'max:999999999999'],
            'due_type' => ['required', Rule::enum(InstallmentDueType::class)],
            'due_date' => ['nullable', 'required_if:due_type,date', 'date'],
            'stage_id' => ['nullable', 'required_if:due_type,stage', 'integer', Rule::exists('project_stages', 'id')->where('project_id', $project?->id)->whereNull('deleted_at')],
        ];
    }
}
