<?php

namespace App\Actions\Finance;

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\ProjectInstallment;
use App\Models\User;
use App\Services\Finance\InvoiceCalculator;
use App\Settings\FinanceSettings;
use App\Settings\TemplateSettings;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * "Create invoice" on a due installment (spec §8.8): a draft with one line whose total is the
 * installment amount exactly (installments include VAT). A second click returns the same invoice.
 */
class CreateInvoiceFromInstallment
{
    public function __construct(
        private readonly FinanceSettings $finance,
        private readonly TemplateSettings $templates,
    ) {}

    /**
     * @throws DomainException with a translated message when the installment is not due
     */
    public function handle(ProjectInstallment $installment, User $by): Invoice
    {
        return DB::transaction(function () use ($installment, $by) {
            $locked = ProjectInstallment::query()->with('project')->lockForUpdate()->findOrFail($installment->id);

            $existing = Invoice::query()->where('installment_id', $locked->id)
                ->where('status', '!=', InvoiceStatus::Cancelled->value)
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            if ($locked->status !== InstallmentStatus::Due) {
                throw new DomainException(__('finance.invoices.installment_not_due'));
            }

            $project = $locked->project;
            $totals = InvoiceCalculator::fromGross((string) $locked->amount, (string) $project->vat_rate, $this->finance->decimals);

            $invoice = new Invoice([
                'project_id' => $project->id,
                'issue_date' => today(),
                'due_date' => today()->addDays($this->finance->invoice_due_days),
                'terms_ar' => $this->templates->invoice_terms_ar,
                'terms_en' => $this->templates->invoice_terms_en,
            ]);
            $invoice->forceFill([
                'client_id' => $project->client_id,
                'installment_id' => $locked->id,
                'status' => InvoiceStatus::Draft,
                'vat_rate' => $project->vat_rate,
                'subtotal' => $totals->subtotal,
                'vat_amount' => $totals->vat,
                'total' => $totals->total,
                'created_by' => $by->id,
            ])->save();

            $invoice->items()->create([
                'description_ar' => $locked->label_ar,
                'description_en' => $locked->label_en,
                'quantity' => '1',
                'unit_price' => $totals->subtotal,
                'line_total' => $totals->subtotal,
                'sort_order' => 1,
            ]);

            return $invoice;
        });
    }
}
