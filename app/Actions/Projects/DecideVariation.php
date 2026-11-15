<?php

namespace App\Actions\Projects;

use App\Enums\InstallmentDueType;
use App\Enums\InstallmentStatus;
use App\Enums\VariationStatus;
use App\Models\Project;
use App\Models\User;
use App\Models\Variation;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Approving a variation changes the contract value once and adds it to the payment schedule
 * (spec §8.9); a project billed by claims gets no installment, its claims carry the variation items. Only a proposed variation can be decided; both rows are locked while it happens.
 */
class DecideVariation
{
    public function approve(Variation $variation, User $by): void
    {
        DB::transaction(function () use ($variation, $by) {
            $locked = $this->lockProposed($variation);
            $project = Project::query()->lockForUpdate()->findOrFail($locked->project_id);

            $project->variations_total = bcadd((string) $project->variations_total, (string) $locked->total, 2);
            $project->contract_value = bcadd((string) $project->original_value, (string) $project->variations_total, 2);
            $project->save();

            if (! $project->isClaimsBilled()) {
                $project->installments()->create([
                    'label_ar' => __('projects.variations.installment_label', ['number' => $locked->number], 'ar'),
                    'label_en' => __('projects.variations.installment_label', ['number' => $locked->number], 'en'),
                    'percent' => null,
                    'amount' => $locked->total,
                    'due_type' => InstallmentDueType::Date,
                    'due_date' => today(),
                    'status' => InstallmentStatus::NotDue,
                    'variation_id' => $locked->id,
                    'sort_order' => (int) $project->installments()->max('sort_order') + 1,
                ]);
            }

            $this->decide($locked, VariationStatus::Approved, $by);
        });
    }

    public function reject(Variation $variation, ?string $note, User $by): void
    {
        DB::transaction(function () use ($variation, $note, $by) {
            $locked = $this->lockProposed($variation);
            $locked->rejection_note = $note;
            $this->decide($locked, VariationStatus::Rejected, $by);
        });
    }

    private function lockProposed(Variation $variation): Variation
    {
        $locked = Variation::query()->lockForUpdate()->findOrFail($variation->id);

        if ($locked->isDecided()) {
            throw new LogicException('The variation was already decided.');
        }

        return $locked;
    }

    private function decide(Variation $variation, VariationStatus $status, User $by): void
    {
        $variation->forceFill(['status' => $status, 'decided_at' => now(), 'decided_by' => $by->id])->save();

        ActivityLogger::record('status_changed', $variation, ['old' => ['status' => 'proposed'], 'attributes' => ['status' => $status->value]], 'projects');
    }
}
