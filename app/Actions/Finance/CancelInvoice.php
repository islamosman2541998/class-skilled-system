<?php

namespace App\Actions\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Finance\ProjectLedger;
use App\Settings\FinanceSettings;
use Closure;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Cancels an issued invoice with a reason (spec §8.10), optionally opening a replacement draft
 * with the same items. An invoice with collected amounts must have its receipts cancelled first.
 */
class CancelInvoice
{
    /**
     * Run inside the transaction after an invoice is cancelled (installment status).
     *
     * @var list<Closure(Invoice, User): void>
     */
    public static array $afterCancel = [];

    public function __construct(private readonly FinanceSettings $finance) {}

    /**
     * @throws DomainException with a translated message when the invoice cannot be cancelled
     */
    public function handle(Invoice $invoice, string $reason, User $by, bool $replace = false): ?Invoice
    {
        return DB::transaction(function () use ($invoice, $reason, $by, $replace) {
            $locked = Invoice::query()->with('items')->lockForUpdate()->findOrFail($invoice->id);

            if ($locked->status === InvoiceStatus::Cancelled) {
                return null;
            }

            if ($locked->isDraft()) {
                throw new DomainException(__('finance.invoices.draft_not_cancelled'));
            }

            if (bccomp((string) $locked->paid_amount, '0', 2) > 0) {
                throw new DomainException(__('finance.invoices.has_receipts'));
            }

            $from = $locked->status;
            $locked->status = InvoiceStatus::Cancelled;
            $locked->cancel_reason = $reason;
            $locked->cancelled_at = now();
            $locked->cancelled_by = $by->id;
            $locked->save();

            ActivityLogger::record('status_changed', $locked, ['old' => ['status' => $from->value], 'attributes' => ['status' => InvoiceStatus::Cancelled->value, 'cancel_reason' => $reason]], 'invoices');

            foreach (self::$afterCancel as $hook) {
                $hook($locked, $by);
            }

            ProjectLedger::refresh($locked->project);

            return $replace ? $this->replacement($locked, $by) : null;
        });
    }

    private function replacement(Invoice $cancelled, User $by): Invoice
    {
        $copy = new Invoice([
            'project_id' => $cancelled->project_id,
            'issue_date' => today(),
            'due_date' => today()->addDays($this->finance->invoice_due_days),
            'notes' => $cancelled->notes,
            'terms_ar' => $cancelled->terms_ar,
            'terms_en' => $cancelled->terms_en,
        ]);
        $copy->forceFill([
            'client_id' => $cancelled->client_id,
            'installment_id' => $cancelled->installment_id,
            'claim_id' => $cancelled->claim_id,
            'replaces_id' => $cancelled->id,
            'status' => InvoiceStatus::Draft,
            'vat_rate' => $cancelled->vat_rate,
            'subtotal' => $cancelled->subtotal,
            'vat_amount' => $cancelled->vat_amount,
            'total' => $cancelled->total,
            'created_by' => $by->id,
        ])->save();

        foreach ($cancelled->items as $item) {
            $copy->items()->create($item->only(['description_ar', 'description_en', 'unit_id', 'quantity', 'unit_price', 'line_total', 'sort_order']));
        }

        return $copy;
    }
}
