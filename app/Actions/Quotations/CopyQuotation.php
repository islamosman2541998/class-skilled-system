<?php

namespace App\Actions\Quotations;

use App\Enums\DocumentType;
use App\Enums\QuotationStatus;
use App\Models\Quotation;
use App\Models\User;
use App\Services\NumberGenerator;
use App\Settings\FinanceSettings;
use Illuminate\Support\Facades\DB;

/**
 * A new, independent quotation from any existing one (spec §8.5 "نسخ إلى عرض جديد"): new number,
 * draft, today's dates and the current VAT rate; totals are recomputed.
 */
class CopyQuotation
{
    public function __construct(
        private readonly SaveQuotation $save,
        private readonly NumberGenerator $numbers,
        private readonly FinanceSettings $finance,
    ) {}

    public function handle(Quotation $quotation, User $by): Quotation
    {
        return DB::transaction(function () use ($quotation, $by) {
            $quotation->load(['items', 'payments']);

            $copy = $quotation->replicate(['number', 'revision', 'parent_id', 'superseded_at', 'status', 'sent_at', 'accepted_at', 'rejected_at', 'rejection_reason_id', 'rejection_note', 'created_by', 'vat_rate']);
            $copy->number = $this->numbers->next(DocumentType::Quotation);
            $copy->revision = 0;
            $copy->status = QuotationStatus::Draft;
            $copy->vat_rate = $this->finance->vat_rate;
            $copy->issue_date = today();
            $copy->valid_until = today()->addDays($this->finance->quotation_validity_days);
            $copy->assigned_to = $by->id;
            $copy->created_by = $by->id;

            $this->save->copyLines($quotation, $copy);

            return $copy;
        });
    }
}
