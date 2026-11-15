<?php

namespace App\Actions\Site;

use App\Enums\ClaimStatus;
use App\Enums\VariationStatus;
use App\Models\Claim;
use App\Models\ClaimLine;
use App\Models\Project;
use App\Models\User;
use App\Models\VariationItem;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Opens a draft claim (spec §8.14): the contract items and the items of approved variations,
 * each with the cumulative quantity of the latest approved or invoiced claim as previous.
 * One open claim per project; the project row is locked, so two clicks open one claim.
 */
class StartClaim
{
    /**
     * @throws DomainException with a translated message
     */
    public function handle(Project $project, User $by): Claim
    {
        return DB::transaction(function () use ($project, $by) {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);

            if (! $locked->isClaimsBilled()) {
                throw new DomainException(__('site.claims.not_claims_billed'));
            }

            if ($locked->claims()->whereIn('status', [ClaimStatus::Draft->value, ClaimStatus::Submitted->value])->exists()) {
                throw new DomainException(__('site.claims.open_exists'));
            }

            $items = $locked->items()->orderBy('sort_order')->orderBy('id')->get();
            $variationItems = VariationItem::query()
                ->whereHas('variation', fn ($query) => $query->where('project_id', $locked->id)->where('status', VariationStatus::Approved->value))
                ->orderBy('variation_id')->orderBy('sort_order')->orderBy('id')
                ->get();

            if ($items->isEmpty() && $variationItems->isEmpty()) {
                throw new DomainException(__('site.claims.no_lines'));
            }

            $last = $locked->claims()->whereIn('status', ClaimStatus::countedValues())->latest('period_to')->latest('id')->with('lines')->first();
            $previousItem = $last?->lines->whereNotNull('project_item_id')->pluck('cumulative_quantity', 'project_item_id') ?? collect();
            $previousVariation = $last?->lines->whereNotNull('variation_item_id')->pluck('cumulative_quantity', 'variation_item_id') ?? collect();

            $from = $last !== null ? $last->period_to->copy()->addDay() : ($locked->start_date ?? today());

            $claim = new Claim;
            $claim->forceFill([
                'project_id' => $locked->id,
                'client_id' => $locked->client_id,
                'period_from' => $from->gt(today()) ? today() : $from,
                'period_to' => today(),
                'status' => ClaimStatus::Draft,
                'vat_rate' => $locked->vat_rate,
                'retention_percent' => $locked->retention_percent,
                'created_by' => $by->id,
            ])->save();

            $order = 0;

            foreach ($items as $item) {
                $this->line($claim, ++$order, ['project_item_id' => $item->id], $item->only(['description_ar', 'description_en', 'unit_id']),
                    (string) $item->quantity, (string) $item->unit_price, (string) ($previousItem[$item->id] ?? '0'));
            }

            foreach ($variationItems as $item) {
                $this->line($claim, ++$order, ['variation_item_id' => $item->id], $item->only(['description_ar', 'description_en', 'unit_id']),
                    (string) $item->quantity, (string) $item->unit_price, (string) ($previousVariation[$item->id] ?? '0'));
            }

            SaveClaim::recompute($claim);

            return $claim;
        });
    }

    /**
     * @param  array<string, int>  $source
     * @param  array<string, mixed>  $text
     */
    private function line(Claim $claim, int $order, array $source, array $text, string $contract, string $price, string $previous): ClaimLine
    {
        return $claim->lines()->create([
            ...$source,
            ...$text,
            'contract_quantity' => $contract,
            'unit_price' => $price,
            'previous_quantity' => $previous,
            'current_quantity' => '0',
            'cumulative_quantity' => $previous,
            'current_amount' => '0',
            'cumulative_amount' => '0',
            'sort_order' => $order,
        ]);
    }
}
