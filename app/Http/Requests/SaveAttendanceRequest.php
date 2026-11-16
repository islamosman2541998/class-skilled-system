<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The attendance sheet of a project for one day (spec §8.17). Today or a past day.
 */
class SaveAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('attendance.create') || $this->user()->can('attendance.update');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'work_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.today()->toDateString()],
            'rows' => ['required', 'array', 'max:500'],
            'rows.*.status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'rows.*.overtime_hours' => ['nullable', 'numeric', 'min:0', 'max:12'],
        ];
    }
}
