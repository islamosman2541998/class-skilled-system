<?php

namespace App\Actions\Site;

use App\Enums\ClaimStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\Finance\InvoiceCalculator;
use App\Settings\FinanceSettings;
use App\Settings\TemplateSettings;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * The retention release invoice of a project billed by claims (spec §5.9; deferred from Stage 4):
 * after handover, one draft invoice for the retention of the approved and invoiced claims, VAT at the
 * project rate. A second call returns the live one.
 */
class CreateRetentionInvoice
{
    public function __construct(
        private readonly FinanceSettings $finance,
        private readonly TemplateSettings $templates,
    ) {}

    /**
     * @throws DomainException with a translated message when there is no handover or nothing retained
     */
    public function handle(Project $project, User $by): Invoice
    {
        return DB::transaction(function () use ($project, $by) {
            $locked = Project::query()->lockForUpdate()->findOrFail($project->id);

            $existing = Invoice::query()->where('project_id', $locked->id)->where('retention_release', true)
                ->where('status', '!=', InvoiceStatus::Cancelled->value)->latest('id')->first();

            if ($existing !== null) {
                return $existing;
            }

            if (! $locked->isClaimsBilled() || ! $locked->handover()->exists()) {
                throw new DomainException(__('site.retention.no_handover'));
            }

            $retained = bcadd((string) $locked->claims()->whereIn('status', ClaimStatus::countedValues())->sum('retention_amount'), '0', 2);

            if (bccomp($retained, '0', 2) <= 0) {
                throw new DomainException(__('site.retention.nothing'));
            }

            $totals = InvoiceCalculator::fromLines([['quantity' => '1', 'unit_price' => $retained]], (string) $locked->vat_rate, $this->finance->decimals);

            $invoice = new Invoice([
                'project_id' => $locked->id,
                'issue_date' => today(),
                'due_date' => today()->addDays($this->finance->invoice_due_days),
                'terms_ar' => $this->templates->invoice_terms_ar,
                'terms_en' => $this->templates->invoice_terms_en,
            ]);
            $invoice->forceFill([
                'client_id' => $locked->client_id,
                'retention_release' => true,
                'status' => InvoiceStatus::Draft,
                'vat_rate' => $locked->vat_rate,
                'subtotal' => $totals->subtotal,
                'vat_amount' => $totals->vat,
                'total' => $totals->total,
                'created_by' => $by->id,
            ])->save();

            $invoice->items()->create([
                'description_ar' => __('site.retention.line', ['number' => $locked->number], 'ar'),
                'description_en' => __('site.retention.line', ['number' => $locked->number], 'en'),
                'quantity' => '1',
                'unit_price' => $retained,
                'line_total' => $totals->subtotal,
                'sort_order' => 1,
            ]);

            return $invoice;
        });
    }
}
