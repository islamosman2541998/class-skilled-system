<?php

namespace App\Actions\Site;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\ClaimLine;
use App\Models\User;
use App\Services\QuotationCalculator;
use App\Services\Site\ClaimCalculator;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Saves a draft claim (spec §8.14): the period and, per line, the current quantity or the
 * cumulative percent of the contract quantity. The cumulative stays between zero and the
 * contract quantity, with its sign. Totals are recomputed on the server.
 */
class SaveClaim
{
    /**
     * @param  array<string, mixed>  $data  validated SaveClaimRequest data
     *
     * @throws DomainException when the claim is no longer a draft
     * @throws ValidationException per line (lines.{id}) when a cumulative breaks the bound
     */
    public function handle(Claim $claim, array $data, User $by): Claim
    {
        return DB::transaction(function () use ($claim, $data) {
            $locked = Claim::query()->lockForUpdate()->findOrFail($claim->id);

            if ($locked->status !== ClaimStatus::Draft) {
                throw new DomainException(__('site.claims.locked'));
            }

            $locked->fill([
                'period_from' => $data['period_from'],
                'period_to' => $data['period_to'],
                'notes' => $data['notes'] ?? null,
            ])->save();

            $input = (array) ($data['lines'] ?? []);
            $errors = [];

            foreach ($locked->lines as $line) {
                $row = (array) ($input[$line->id] ?? []);
                $current = $this->current($line, $row);

                if ($current === null) {
                    continue;
                }

                $cumulative = bcadd((string) $line->previous_quantity, $current, 3);

                if (! $this->withinContract($cumulative, (string) $line->contract_quantity)) {
                    $errors['lines.'.$line->id] = __('site.claims.beyond_contract', ['line' => $line->description()]);

                    continue;
                }

                $line->current_quantity = $current;
                $line->cumulative_quantity = $cumulative;
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $locked->lines->each(fn (ClaimLine $line) => $line->save());

            try {
                self::recompute($locked);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['lines' => __('site.claims.negative_net')]);
            }

            return $locked;
        });
    }

    /**
     * Current quantity of a line from the posted row: the cumulative percent wins when both are filled.
     *
     * @param  array<string, mixed>  $row
     */
    private function current(ClaimLine $line, array $row): ?string
    {
        if (filled($row['cumulative_percent'] ?? null)) {
            $cumulative = ClaimCalculator::round(bcdiv(bcmul((string) $line->contract_quantity, (string) $row['cumulative_percent'], 10), '100', 10), 3);

            return bcsub($cumulative, (string) $line->previous_quantity, 3);
        }

        if (filled($row['current_quantity'] ?? null)) {
            return bcadd((string) $row['current_quantity'], '0', 3);
        }

        return null;
    }

    private function withinContract(string $cumulative, string $contract): bool
    {
        return bccomp($contract, '0', 3) >= 0
            ? bccomp($cumulative, '0', 3) >= 0 && bccomp($cumulative, $contract, 3) <= 0
            : bccomp($cumulative, '0', 3) <= 0 && bccomp($cumulative, $contract, 3) >= 0;
    }

    /**
     * Line amounts and claim money from the stored quantities, the project setup and the
     * advance already recovered by approved or invoiced claims.
     *
     * @throws InvalidArgumentException when the net would be negative
     */
    public static function recompute(Claim $claim): void
    {
        $claim->load(['lines', 'project']);
        $project = $claim->project;

        $contractWork = $claim->lines->reduce(fn (string $sum, ClaimLine $line) => bcadd($sum,
            ClaimCalculator::round(bcmul((string) $line->contract_quantity, (string) $line->unit_price, 10), 2), 2), '0');
        $contractDiscount = ClaimCalculator::round(bcdiv(bcmul($contractWork, (string) $project->contract_discount_percent, 10), '100', 10), 2);
        $recoveredBefore = (string) $project->claims()->whereIn('status', ClaimStatus::countedValues())->whereKeyNot($claim->id)->sum('advance_recovery');

        $totals = ClaimCalculator::compute(
            $claim->lines->map(fn (ClaimLine $line) => [
                'contract_quantity' => (string) $line->contract_quantity,
                'unit_price' => (string) $line->unit_price,
                'previous_quantity' => (string) $line->previous_quantity,
                'current_quantity' => (string) $line->current_quantity,
            ])->values()->all(),
            (string) $project->contract_discount_percent,
            (string) $claim->retention_percent,
            (string) $project->advance_amount,
            QuotationCalculator::round($recoveredBefore, 2),
            bcsub($contractWork, $contractDiscount, 2),
            (string) $claim->vat_rate,
        );

        foreach ($claim->lines->values() as $index => $line) {
            $line->current_amount = $totals->lineAmounts[$index]['current'];
            $line->cumulative_amount = $totals->lineAmounts[$index]['cumulative'];
            $line->save();
        }

        $claim->forceFill([
            'work_previous' => $totals->workPrevious,
            'work_current' => $totals->workCurrent,
            'work_cumulative' => $totals->workCumulative,
            'discount_amount' => $totals->discount,
            'gross_amount' => $totals->gross,
            'retention_amount' => $totals->retention,
            'advance_recovery' => $totals->advanceRecovery,
            'net_amount' => $totals->net,
            'vat_amount' => $totals->vat,
            'total' => $totals->total,
        ])->save();
    }
}
