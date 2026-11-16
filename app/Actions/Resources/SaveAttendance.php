<?php

namespace App\Actions\Resources;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\User;
use App\Models\Worker;
use App\Services\Finance\ProjectLedger;
use App\Services\Resources\LabourCost;
use App\Settings\FinanceSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves the attendance sheet of a project for one day (spec §8.17). A worker has one row a day,
 * on one project; a blank status removes the worker's row for this project and day. An existing
 * row keeps its wage unless its status or overtime changes.
 */
class SaveAttendance
{
    public function __construct(private readonly FinanceSettings $finance) {}

    /**
     * @param  array<int|string, array{status?: string|null, overtime_hours?: string|int|float|null}>  $rows  worker id => row
     *
     * @throws ValidationException on "rows.{worker}" when the worker is on another project that day
     */
    public function handle(Project $project, Carbon $date, array $rows, User $by): void
    {
        try {
            $this->save($project, $date, $rows, $by);
        } catch (UniqueConstraintViolationException) {
            // The same worker was saved on another sheet at the same moment.
            throw ValidationException::withMessages(['rows' => __('resources.attendance.saved_elsewhere')]);
        }
    }

    /**
     * @param  array<int|string, array{status?: string|null, overtime_hours?: string|int|float|null}>  $rows
     */
    private function save(Project $project, Carbon $date, array $rows, User $by): void
    {
        DB::transaction(function () use ($project, $date, $rows, $by) {
            // The project row lock serialises two sheets of the same project.
            Project::query()->lockForUpdate()->findOrFail($project->id);
            $day = $date->toDateString();
            $workerIds = array_map('intval', array_keys($rows));
            $workers = Worker::withTrashed()->whereIn('id', $workerIds)->get()->keyBy('id');
            $existing = Attendance::query()->whereIn('worker_id', $workerIds)->whereDate('work_date', $day)->lockForUpdate()->get()->keyBy('worker_id');
            $errors = [];

            foreach ($rows as $workerId => $row) {
                $workerId = (int) $workerId;
                $status = AttendanceStatus::tryFrom((string) ($row['status'] ?? ''));
                $current = $existing->get($workerId);

                if ($current !== null && $current->project_id !== $project->id) {
                    if ($status !== null) {
                        $errors['rows.'.$workerId] = __('resources.attendance.on_other_project', ['project' => $current->project->number]);
                    }

                    continue;
                }

                if ($status === null) {
                    $current?->delete();

                    continue;
                }

                $worker = $workers->get($workerId);

                if ($worker === null) {
                    continue;
                }

                $hours = bcadd((string) ($row['overtime_hours'] ?? '0') ?: '0', '0', 2);
                $unchanged = $current !== null && $current->status === $status && bccomp((string) $current->overtime_hours, $hours, 2) === 0;

                if ($unchanged) {
                    continue;
                }

                $attendance = $current ?? new Attendance;
                $attendance->forceFill([
                    'project_id' => $project->id,
                    'worker_id' => $workerId,
                    'work_date' => $day,
                    'status' => $status,
                    'overtime_hours' => $hours,
                    'daily_wage' => $current->daily_wage ?? $worker->daily_wage,
                    'created_by' => $current->created_by ?? $by->id,
                ]);
                $attendance->cost = LabourCost::compute((string) $attendance->daily_wage, $status, $hours, $this->finance->labour_hours_per_day, $this->finance->overtime_multiplier);
                $attendance->save();
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            ProjectLedger::refresh($project);
        });
    }
}
