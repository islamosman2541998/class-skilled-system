<?php

namespace App\Actions\Finance;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Services\Finance\InvoiceCalculator;
use App\Settings\FinanceSettings;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Creates or updates a draft invoice with its items; totals are computed here (spec §5.9).
 * The VAT rate is the project's, copied when the invoice is created.
 */
class SaveInvoice
{
    public function __construct(private readonly FinanceSettings $finance) {}

    /**
     * @param  array<string, mixed>  $data  validated SaveInvoiceRequest data
     */
    public function handle(array $data, User $by, ?Invoice $invoice = null): Invoice
    {
        if ($invoice !== null && ! $invoice->isDraft()) {
            throw new LogicException('Only a draft invoice can be edited.');
        }

        return DB::transaction(function () use ($data, $by, $invoice) {
            $creating = $invoice === null;
            $invoice ??= new Invoice;
            $project = Project::query()->findOrFail($data['project_id']);

            $invoice->fill([
                'project_id' => $project->id,
                'issue_date' => $data['issue_date'],
                'due_date' => $data['due_date'],
                'notes' => $data['notes'] ?? null,
                'terms_ar' => $data['terms_ar'] ?? null,
                'terms_en' => $data['terms_en'] ?? null,
            ]);
            $invoice->client_id = $project->client_id;

            if ($creating) {
                $invoice->status = InvoiceStatus::Draft;
                $invoice->vat_rate = (string) $project->vat_rate;
                $invoice->created_by = $by->id;
                $invoice->installment_id = $data['installment_id'] ?? null;
                $invoice->replaces_id = $data['replaces_id'] ?? null;
            }

            $items = array_values($data['items']);
            $totals = InvoiceCalculator::fromLines($items, (string) $invoice->vat_rate, $this->finance->decimals);

            $invoice->subtotal = $totals->subtotal;
            $invoice->vat_amount = $totals->vat;
            $invoice->total = $totals->total;
            $invoice->save();

            $invoice->items()->delete();

            foreach ($items as $index => $item) {
                $invoice->items()->create([
                    'description_ar' => $item['description_ar'],
                    'description_en' => $item['description_en'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => (string) $item['quantity'],
                    'unit_price' => (string) $item['unit_price'],
                    'line_total' => $totals->lineTotals[$index],
                    'sort_order' => $index + 1,
                ]);
            }

            return $invoice;
        });
    }
}
