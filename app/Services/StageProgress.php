<?php

namespace App\Services;

use App\Enums\StageStatus;
use App\Models\ProjectStage;
use Illuminate\Support\Carbon;

/**
 * One rule for a stage's progress and status (spec §8.7), used by the stage form and by daily
 * reports (§8.13): done means 100, 100 means done, any progress means started. Actual start and
 * end take the given date. Callers save the stage and refresh the project progress.
 */
final class StageProgress
{
    public static function apply(ProjectStage $stage, ?string $progress, ?StageStatus $status, Carbon $on): void
    {
        $progress ??= (string) ($stage->progress ?? '0');

        if ($status === StageStatus::Done || bccomp($progress, '100', 2) >= 0) {
            $status = StageStatus::Done;
            $progress = '100';
        } elseif ($status === null || ($status === StageStatus::NotStarted && bccomp($progress, '0', 2) > 0)) {
            $status = bccomp($progress, '0', 2) > 0 ? StageStatus::InProgress : StageStatus::NotStarted;
        }

        $stage->status = $status;
        $stage->progress = $progress;

        if ($status !== StageStatus::NotStarted && $stage->actual_start === null) {
            $stage->actual_start = $on->copy();
        }

        if ($status === StageStatus::Done && $stage->actual_end === null) {
            $stage->actual_end = $on->copy();
        }
    }
}
