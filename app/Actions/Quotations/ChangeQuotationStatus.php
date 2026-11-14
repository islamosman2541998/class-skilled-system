<?php

namespace App\Actions\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The status moves of a quotation (spec §8.5): send, accept, reject. Only an open (draft or sent,
 * not superseded) quotation moves; every move is logged.
 */
class ChangeQuotationStatus
{
    public function send(Quotation $quotation, User $by): void
    {
        $this->move($quotation, QuotationStatus::Sent, $by, ['sent_at' => now()]);
    }

    public function accept(Quotation $quotation, User $by): void
    {
        DB::transaction(function () use ($quotation, $by) {
            $this->move($quotation, QuotationStatus::Accepted, $by, ['accepted_at' => now()]);
            $quotation->lead?->setStatusCode('won', $by);
        });
    }

    public function reject(Quotation $quotation, int $reasonId, ?string $note, User $by): void
    {
        $this->move($quotation, QuotationStatus::Rejected, $by, [
            'rejected_at' => now(),
            'rejection_reason_id' => $reasonId,
            'rejection_note' => $note,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function move(Quotation $quotation, QuotationStatus $to, User $by, array $attributes): void
    {
        if ($quotation->isLocked() || ($to === QuotationStatus::Sent && $quotation->status !== QuotationStatus::Draft)) {
            throw new LogicException('The quotation cannot move to '.$to->value);
        }

        $from = $quotation->status;
        $quotation->forceFill(['status' => $to, ...$attributes])->save();

        ActivityLogger::record('status_changed', $quotation, ['old' => ['status' => $from->value], 'attributes' => ['status' => $to->value]], 'quotations');
    }
}
