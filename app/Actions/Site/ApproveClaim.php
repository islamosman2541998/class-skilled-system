<?php

namespace App\Actions\Site;

use App\Enums\ClaimStatus;
use App\Enums\InvoiceStatus;
use App\Models\Claim;
use App\Models\Invoice;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Settings\FinanceSettings;
use App\Settings\TemplateSettings;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Approves a submitted claim (spec §8.14 "المعتمد يُنشئ فاتورة"): a draft invoice with one line
 * whose subtotal, VAT and total are the claim's own. An approved claim returns its live invoice,
 * or a new draft when that invoice was deleted, so a second click never makes a second invoice.
 */
class ApproveClaim
{
    public function __construct(
        private readonly FinanceSettings $finance,
        private readonly TemplateSettings $templates,
    ) {}

    /**
     * @throws DomainException with a translated message when the claim is not submitted or approved
     */
    public function handle(Claim $claim, User $by): Invoice
    {
        return DB::transaction(function () use ($claim, $by) {
            $locked = Claim::query()->lockForUpdate()->findOrFail($claim->id);

            if (in_array($locked->status, [ClaimStatus::Approved, ClaimStatus::Invoiced], true)) {
                $existing = $locked->invoice()->first();

                if ($existing !== null || $locked->status === ClaimStatus::Invoiced) {
                    return $existing ?? throw new DomainException(__('site.claims.not_submitted'));
                }

                return $this->invoice($locked, $by);
            }

            if ($locked->status !== ClaimStatus::Submitted) {
                throw new DomainException(__('site.claims.not_submitted'));
            }

            $locked->status = ClaimStatus::Approved;
            $locked->approved_at = now();
            $locked->approved_by = $by->id;
            $locked->save();

            ActivityLogger::record('status_changed', $locked, ['old' => ['status' => ClaimStatus::Submitted->value], 'attributes' => ['status' => ClaimStatus::Approved->value]], 'claims');

            return $this->invoice($locked, $by);
        });
    }

    private function invoice(Claim $claim, User $by): Invoice
    {
        $replace = [
            'number' => (string) $claim->number,
            'from' => fmt_date($claim->period_from),
            'to' => fmt_date($claim->period_to),
        ];

        $invoice = new Invoice([
            'project_id' => $claim->project_id,
            'issue_date' => today(),
            'due_date' => today()->addDays($this->finance->invoice_due_days),
            'terms_ar' => $this->templates->invoice_terms_ar,
            'terms_en' => $this->templates->invoice_terms_en,
        ]);
        $invoice->forceFill([
            'client_id' => $claim->client_id,
            'claim_id' => $claim->id,
            'status' => InvoiceStatus::Draft,
            'vat_rate' => $claim->vat_rate,
            'subtotal' => $claim->net_amount,
            'vat_amount' => $claim->vat_amount,
            'total' => $claim->total,
            'created_by' => $by->id,
        ])->save();

        $invoice->items()->create([
            'description_ar' => __('site.claims.invoice_line', $replace, 'ar'),
            'description_en' => __('site.claims.invoice_line', $replace, 'en'),
            'quantity' => '1',
            'unit_price' => $claim->net_amount,
            'line_total' => $claim->net_amount,
            'sort_order' => 1,
        ]);

        return $invoice;
    }
}
