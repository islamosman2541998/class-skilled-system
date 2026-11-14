<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Project;
use Closure;

/**
 * Keeps the money summary columns of a project (spec §8.6, §11): invoiced (with and without VAT),
 * collected and cost. Lists and pages read these columns, never live sums.
 */
class ProjectLedger
{
    /**
     * Cost amounts before VAT, one closure per source (expenses; purchases and labour later).
     *
     * @var array<string, Closure(Project): string>
     */
    public static array $costSources = [];

    /**
     * Collected amounts, one closure per source (receipts).
     *
     * @var array<string, Closure(Project): string>
     */
    public static array $collectedSources = [];

    public static function refresh(Project $project): void
    {
        $invoices = Invoice::query()->where('project_id', $project->id)->active();

        $values = [
            'invoiced_total' => self::money((clone $invoices)->sum('total')),
            'invoiced_net' => self::money((clone $invoices)->sum('subtotal')),
            'collected_total' => self::sumOf(self::$collectedSources, $project),
            'cost_total' => self::sumOf(self::$costSources, $project),
        ];

        // A direct update: summary figures are not user edits, so no activity entry or events.
        Project::withTrashed()->whereKey($project->id)->update($values);
        $project->forceFill($values)->syncOriginalAttributes(array_keys($values));
    }

    /**
     * @param  array<string, Closure(Project): string>  $sources
     */
    private static function sumOf(array $sources, Project $project): string
    {
        $sum = '0';

        foreach ($sources as $source) {
            $sum = bcadd($sum, self::money($source($project)), 2);
        }

        return self::money($sum);
    }

    private static function money(mixed $value): string
    {
        return bcadd(is_numeric($value) ? (string) $value : '0', '0', 2);
    }
}
