<?php

namespace App\Http\Requests;

use App\Enums\ProjectStatus;
use App\Models\DailyReport;
use App\Models\Project;
use App\Models\ProjectStage;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A daily report (spec §8.13): a project the user sees that is not closed, today or a past day,
 * and progress 0–100 for stages of that project only.
 */
class SaveDailyReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can($this->route('daily_report') instanceof DailyReport ? 'daily_reports.update' : 'daily_reports.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $closed = array_values(array_map(fn (ProjectStatus $status) => $status->value, array_filter(ProjectStatus::cases(), fn (ProjectStatus $status) => $status->isClosed())));

        return [
            'project_id' => ['required', 'integer', function (string $attribute, mixed $value, Closure $fail) use ($user, $closed) {
                if (! Project::query()->visibleTo($user)->whereNotIn('status', $closed)->whereKey($value)->exists()) {
                    $fail(__('validation.exists', ['attribute' => __('site.reports.project')]));
                }
            }],
            'report_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.today()->toDateString()],
            'work_done' => ['required', 'string', 'max:5000'],
            'labour_count' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'obstacles' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'stages' => ['nullable', 'array'],
            'stages.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'uploads' => ['nullable', 'array', 'max:30'],
            'uploads.*' => ['string', 'uuid'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $ids = array_map('intval', array_keys((array) $this->input('stages', [])));

                if ($ids === [] || $validator->errors()->has('project_id')) {
                    return;
                }

                $own = ProjectStage::query()->where('project_id', (int) $this->input('project_id'))->whereIn('id', $ids)->count();

                if ($own !== count(array_unique($ids))) {
                    $validator->errors()->add('stages', __('site.reports.foreign_stage'));
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'report_date' => __('site.reports.date'),
            'work_done' => __('site.reports.work_done'),
            'labour_count' => __('site.reports.labour_count'),
        ];
    }
}
