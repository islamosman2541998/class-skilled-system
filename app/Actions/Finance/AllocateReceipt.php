<?php

namespace App\Actions\Finance;

use App\Models\Invoice;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Finance\InvoiceBalance;
use App\Services\Finance\ProjectLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the allocations of a receipt (spec §8.11). Every invoice row is locked, so two receipts
 * paying the same invoice at the same moment can never collect more than its total.
 */
class AllocateReceipt
{
    /**
     * @param  array<int|string, mixed>  $amounts  invoice id => amount; blank or zero means none
     *
     * @throws ValidationException on the "allocations" key
     */
    public function handle(Receipt $receipt, array $amounts, User $by): void
    {
        DB::transaction(function () use ($receipt, $amounts) {
            $locked = Receipt::query()->with('allocations')->lockForUpdate()->findOrFail($receipt->id);

            if (! $locked->isActive()) {
                throw ValidationException::withMessages(['allocations' => __('finance.receipts.cancelled_locked')]);
            }

            $wanted = [];

            foreach ($amounts as $invoiceId => $amount) {
                if (is_numeric($amount) && bccomp((string) $amount, '0', 2) > 0) {
                    $wanted[(int) $invoiceId] = bcadd((string) $amount, '0', 2);
                }
            }

            $current = $locked->allocations->pluck('amount', 'invoice_id')->map(fn ($amount) => (string) $amount)->all();
            $ids = array_values(array_unique([...array_keys($current), ...array_keys($wanted)]));
            $invoices = Invoice::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $sum = '0';

            foreach ($wanted as $invoiceId => $amount) {
                $invoice = $invoices->get($invoiceId);

                if ($invoice === null || ! $invoice->status->isActive() || $invoice->client_id !== $locked->client_id
                    || ($locked->project_id !== null && $invoice->project_id !== $locked->project_id)) {
                    throw ValidationException::withMessages(['allocations' => __('finance.receipts.invalid_invoice')]);
                }

                // What this receipt already pays on the invoice is given back before the check.
                $open = bcadd($invoice->remaining(), $current[$invoiceId] ?? '0', 2);

                if (bccomp($amount, $open, 2) > 0) {
                    throw ValidationException::withMessages(['allocations' => __('finance.receipts.exceeds_invoice', ['number' => $invoice->number, 'amount' => money($open)])]);
                }

                $sum = bcadd($sum, $amount, 2);
            }

            if (bccomp($sum, (string) $locked->amount, 2) > 0) {
                throw ValidationException::withMessages(['allocations' => __('finance.receipts.exceeds_receipt', ['amount' => money($locked->amount)])]);
            }

            $locked->allocations()->delete();

            foreach ($wanted as $invoiceId => $amount) {
                $locked->allocations()->create(['invoice_id' => $invoiceId, 'amount' => $amount]);
            }

            foreach ($invoices as $invoice) {
                InvoiceBalance::refresh($invoice);
            }

            if ($locked->project_id !== null) {
                ProjectLedger::refresh(Project::query()->findOrFail($locked->project_id));
            }
        });
    }
}
