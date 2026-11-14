<?php

namespace App\Services;

use App\Enums\InstallmentDueType;
use App\Enums\InstallmentStatus;
use App\Enums\StageStatus;
use App\Models\Project;
use App\Models\ProjectInstallment;
use App\Support\QuotationTotals;

/**
 * A project's payment schedule (spec §8.8): building it from percentages, the gap against the
 * contract value, and moving installments to "due".
 */
final class InstallmentSchedule
{
    /**
     * Replaces nothing: appends one installment per line, amounts from the contract value with the
     * last line taking the rounding remainder. Lines due on a date get today when none is given.
     *
     * @param  list<array{label_ar: string, label_en?: ?string, percent: string|int|float}>  $lines
     */
    public static function fromPercents(Project $project, array $lines): void
    {
        $amounts = self::amounts((string) $project->contract_value, array_map(fn (array $line) => (string) $line['percent'], $lines));
        $order = (int) $project->installments()->max('sort_order');

        foreach ($lines as $index => $line) {
            $project->installments()->create([
                'label_ar' => $line['label_ar'],
                'label_en' => $line['label_en'] ?? null,
                'percent' => (string) $line['percent'],
                'amount' => $amounts[$index],
                'due_type' => InstallmentDueType::Date,
                'due_date' => null,
                'status' => InstallmentStatus::NotDue,
                'sort_order' => $order + $index + 1,
            ]);
        }
    }

    /** Contract value minus the sum of the schedule; "0.00" when they match. */
    public static function difference(Project $project): string
    {
        return bcsub((string) $project->contract_value, (string) $project->installments()->sum('amount'), 2);
    }

    /** Amount for a percentage of the contract value, rounded half up. */
    public static function amountFor(Project $project, string $percent): string
    {
        return QuotationCalculator::round(bcdiv(bcmul((string) $project->contract_value, $percent, 8), '100', 8), 2);
    }

    /** Percentage an amount is of the contract value (null when the value is 0). */
    public static function percentFor(Project $project, string $amount): ?string
    {
        if (bccomp((string) $project->contract_value, '0', 2) <= 0) {
            return null;
        }

        return QuotationCalculator::round(bcdiv(bcmul($amount, '100', 8), (string) $project->contract_value, 8), 2);
    }

    /** Moves not-due installments whose date arrived, or whose stage is done, to due. Returns how many moved. */
    public static function markDue(?Project $project = null): int
    {
        $query = ProjectInstallment::query()
            ->where('status', InstallmentStatus::NotDue)
            ->when($project, fn ($q) => $q->where('project_id', $project->id))
            ->where(fn ($q) => $q
                ->where(fn ($date) => $date->where('due_type', InstallmentDueType::Date)->whereNotNull('due_date')->whereDate('due_date', '<=', today()))
                ->orWhere(fn ($stage) => $stage->where('due_type', InstallmentDueType::Stage)->whereHas('stage', fn ($s) => $s->where('status', StageStatus::Done))));

        return $query->update(['status' => InstallmentStatus::Due->value, 'updated_at' => now()]);
    }

    /**
     * @param  list<string>  $percents
     * @return list<string>
     */
    private static function amounts(string $total, array $percents): array
    {
        return (new QuotationTotals([], $total, '0', $total, '0', $total, 2))->paymentAmounts($percents);
    }
}
