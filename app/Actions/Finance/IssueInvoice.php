<?php

namespace App\Actions\Finance;

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Finance\ProjectLedger;
use App\Services\NumberGenerator;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Issues a draft: reserves its number and locks it against edits (spec §5.9, §8.10).
 * A second call on the same invoice (double click, two tabs) changes nothing.
 */
class IssueInvoice
{
    /**
     * Run inside the transaction after an invoice is issued (installment status).
     *
     * @var list<Closure(Invoice, User): void>
     */
    public static array $afterIssue = [];

    public function __construct(private readonly NumberGenerator $numbers) {}

    public function handle(Invoice $invoice, User $by): Invoice
    {
        return DB::transaction(function () use ($invoice, $by) {
            $locked = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

            if (! $locked->isDraft()) {
                return $locked;
            }

            $locked->number = $this->numbers->next(DocumentType::Invoice, $locked->issue_date);
            $locked->status = InvoiceStatus::Issued;
            $locked->issued_at = now();
            $locked->issued_by = $by->id;
            $locked->save();

            ActivityLogger::record('status_changed', $locked, ['old' => ['status' => InvoiceStatus::Draft->value], 'attributes' => ['status' => InvoiceStatus::Issued->value, 'number' => $locked->number]], 'invoices');

            foreach (self::$afterIssue as $hook) {
                $hook($locked, $by);
            }

            ProjectLedger::refresh($locked->project);

            return $locked;
        });
    }
}
