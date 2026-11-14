<?php

namespace App\Services;

use App\Enums\ProjectStatus;
use App\Enums\StageStatus;
use App\Models\Project;

/**
 * Project progress from its stages (spec §8.6): Σ(weight × progress) / Σ(weight). Weights need not
 * add up to 100; without stages or weights the progress is 0.
 */
final class ProjectProgress
{
    /** @var list<\Closure(Project): void> Run after a project's progress or stage states changed (installments due …). */
    public static array $afterRefresh = [];

    /**
     * @param  iterable<object>  $stages  items with weight and progress
     */
    public static function compute(iterable $stages): string
    {
        $weighted = '0';
        $weights = '0';

        foreach ($stages as $stage) {
            $weight = (string) $stage->weight;
            $weights = bcadd($weights, $weight, 4);
            $weighted = bcadd($weighted, bcmul($weight, (string) $stage->progress, 6), 6);
        }

        if (bccomp($weights, '0', 4) <= 0) {
            return '0.00';
        }

        return QuotationCalculator::round(bcdiv($weighted, $weights, 6), 2);
    }

    public static function refresh(Project $project): void
    {
        $stages = $project->stages()->get(['weight', 'progress', 'status']);
        $project->progress = self::compute($stages);

        // Work started on a stage means the project has started.
        if ($project->status === ProjectStatus::NotStarted && $stages->contains(fn ($stage) => $stage->status !== StageStatus::NotStarted)) {
            $project->status = ProjectStatus::InProgress;
        }

        $project->save();

        foreach (self::$afterRefresh as $hook) {
            $hook($project);
        }
    }
}
