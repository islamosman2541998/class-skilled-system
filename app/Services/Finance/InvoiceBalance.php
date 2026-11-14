<?php

namespace App\Services\Finance;

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\ProjectInstallment;
use App\Models\ReceiptAllocation;

/**
 * Collected amount and collection status of an invoice, from its active receipt allocations
 * (spec §8.10), and the installment state that follows it (spec §8.8).
 */
class InvoiceBalance
{
    public static function refresh(Invoice $invoice): void
    {
        $paid = bcadd((string) ReceiptAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->whereHas('receipt', fn ($receipts) => $receipts->active())
            ->sum('amount'), '0', 2);

        $invoice->paid_amount = $paid;

        if ($invoice->status->isActive()) {
            $invoice->status = match (true) {
                bccomp($paid, '0', 2) <= 0 => InvoiceStatus::Issued,
                bccomp($paid, (string) $invoice->total, 2) >= 0 => InvoiceStatus::Paid,
                default => InvoiceStatus::PartiallyPaid,
            };
        }

        $invoice->save();

        if ($invoice->installment_id !== null && $invoice->status->isActive()) {
            ProjectInstallment::query()->whereKey($invoice->installment_id)->update([
                'status' => ($invoice->status === InvoiceStatus::Paid ? InstallmentStatus::Collected : InstallmentStatus::Invoiced)->value,
            ]);
        }

        ProjectLedger::refresh($invoice->loadMissing('project')->project);
    }
}
