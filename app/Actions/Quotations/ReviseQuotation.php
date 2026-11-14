<?php

namespace App\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * A new revision (R1, R2 …) of an open quotation (spec §8.5). It keeps the root's number with a
 * suffix, so no document number is used; the revised quotation becomes read-only.
 */
class ReviseQuotation
{
    public function __construct(private readonly SaveQuotation $save) {}

    public function handle(Quotation $quotation, User $by): Quotation
    {
        if ($quotation->isLocked()) {
            throw new LogicException('A locked quotation cannot be revised.');
        }

        return DB::transaction(function () use ($quotation, $by) {
            $quotation->load(['items', 'payments']);
            $rootId = $quotation->parent_id ?? $quotation->id;
            $next = (int) Quotation::withTrashed()->where(fn ($q) => $q->whereKey($rootId)->orWhere('parent_id', $rootId))->max('revision') + 1;

            $revision = $quotation->replicate(['number', 'revision', 'parent_id', 'superseded_at', 'status', 'sent_at', 'accepted_at', 'rejected_at', 'rejection_reason_id', 'rejection_note', 'created_by']);
            $revision->number = $quotation->baseNumber().'-R'.$next;
            $revision->revision = $next;
            $revision->parent_id = $rootId;
            $revision->status = QuotationStatus::Draft;
            $revision->created_by = $by->id;

            $this->save->copyLines($quotation, $revision);

            $quotation->forceFill(['superseded_at' => now()])->save();

            return $revision;
        });
    }
}
