<?php

use App\Enums\StageStatus;
use App\Models\ProjectStage;
use App\Services\StageProgress;
use Illuminate\Support\Carbon;

it('done means 100 and 100 means done', function () {
    $done = new ProjectStage(['progress' => '40', 'status' => StageStatus::InProgress]);
    StageProgress::apply($done, null, StageStatus::Done, Carbon::parse('2026-11-10'));

    $full = new ProjectStage(['progress' => '40', 'status' => StageStatus::InProgress]);
    StageProgress::apply($full, '100', null, Carbon::parse('2026-11-11'));

    expect($done->status)->toBe(StageStatus::Done)
        ->and((string) $done->progress)->toBe('100.00')
        ->and($done->actual_end?->toDateString())->toBe('2026-11-10')
        ->and($full->status)->toBe(StageStatus::Done)
        ->and($full->actual_end?->toDateString())->toBe('2026-11-11');
});

it('any progress starts the stage and sets actual start to the given date', function () {
    $stage = new ProjectStage(['progress' => '0', 'status' => StageStatus::NotStarted]);
    StageProgress::apply($stage, '25', null, Carbon::parse('2026-11-03'));

    $untouched = new ProjectStage(['progress' => '0', 'status' => StageStatus::NotStarted]);
    StageProgress::apply($untouched, '0', null, Carbon::parse('2026-11-03'));

    expect($stage->status)->toBe(StageStatus::InProgress)
        ->and((string) $stage->progress)->toBe('25.00')
        ->and($stage->actual_start?->toDateString())->toBe('2026-11-03')
        ->and($stage->actual_end)->toBeNull()
        ->and($untouched->status)->toBe(StageStatus::NotStarted)
        ->and($untouched->actual_start)->toBeNull();
});
