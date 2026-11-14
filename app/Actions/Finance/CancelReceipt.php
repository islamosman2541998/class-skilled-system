<?php

namespace App\Actions\Finance;

use App\Enums\ReceiptStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Finance\InvoiceBalance;
use App\Services\Finance\ProjectLedger;
use Illuminate\Support\Facades\DB;

/**
 * Cancels a receipt with a reason (spec §8.11): its allocations stop counting, so the invoices,
 * their installments and the project figures go back to what the other receipts cover.
 */
class CancelReceipt
{
    public function handle(Receipt $receipt, string $reason, User $by): void
    {
        DB::transaction(function () use ($receipt, $reason, $by) {
            $locked = Receipt::query()->with('allocations')->lockForUpdate()->findOrFail($receipt->id);

            if (! $locked->isActive()) {
                return;
            }

            $locked->status = ReceiptStatus::Cancelled;
            $locked->cancel_reason = $reason;
            $locked->cancelled_at = now();
            $locked->cancelled_by = $by->id;
            $locked->save();

            ActivityLogger::record('status_changed', $locked, ['old' => ['status' => ReceiptStatus::Active->value], 'attributes' => ['status' => ReceiptStatus::Cancelled->value, 'cancel_reason' => $reason]], 'receipts');

            $invoices = Invoice::query()->whereIn('id', $locked->allocations->pluck('invoice_id'))->orderBy('id')->lockForUpdate()->get();

            foreach ($invoices as $invoice) {
                InvoiceBalance::refresh($invoice);
            }

            if ($locked->project_id !== null) {
                ProjectLedger::refresh(Project::query()->findOrFail($locked->project_id));
            }
        });
    }
}
