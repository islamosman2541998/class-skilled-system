<?php

namespace App\Actions\Quotations;

use App\Actions\Leads\EnsureClientForLead;
use App\Enums\DiscountType;
use App\Enums\DocumentType;
use App\Enums\QuotationStatus;
use App\Models\Lead;
use App\Models\Quotation;
use App\Models\User;
use App\Services\NumberGenerator;
use App\Services\QuotationCalculator;
use App\Settings\FinanceSettings;
use App\Support\QuotationTotals;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a quotation with its items and payment schedule in one transaction.
 * Totals always come from QuotationCalculator; anything the browser sent as a total is ignored.
 */
class SaveQuotation
{
    public function __construct(
        private readonly QuotationCalculator $calculator,
        private readonly NumberGenerator $numbers,
        private readonly FinanceSettings $finance,
        private readonly EnsureClientForLead $ensureClient,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated SaveQuotationRequest data
     */
    public function handle(array $data, User $by, ?Quotation $quotation = null): Quotation
    {
        return DB::transaction(function () use ($data, $by, $quotation) {
            $creating = $quotation === null;
            $quotation ??= new Quotation;

            if ($creating && ! empty($data['lead_id'])) {
                $lead = Lead::query()->findOrFail($data['lead_id']);
                $data['client_id'] = $this->ensureClient->handle($lead, $by)->id;
                $lead->setStatusCode('quoted', $by);
            }

            if (! $creating) {
                unset($data['lead_id'], $data['client_id']);
            }

            $quotation->fill($data);

            if ($creating) {
                $quotation->number = $this->numbers->next(DocumentType::Quotation);
                $quotation->status = QuotationStatus::Draft;
                $quotation->vat_rate = $this->finance->vat_rate;
                $quotation->created_by = $by->id;
            }

            $this->applyTotals($quotation, $data['items'], $data['payments']);
            $quotation->save();

            $this->syncLines($quotation, $data['items'], $data['payments']);

            return $quotation;
        });
    }

    /** Copies items and payments onto another quotation (revisions and copies), recomputing totals. */
    public function copyLines(Quotation $from, Quotation $to): void
    {
        $items = $from->items->map(fn ($item) => $item->only(['section_ar', 'section_en', 'price_item_id', 'description_ar', 'description_en', 'unit_id', 'quantity', 'unit_price']))->all();
        $payments = $from->payments->map(fn ($payment) => $payment->only(['label_ar', 'label_en', 'percent']))->all();

        $this->applyTotals($to, $items, $payments);
        $to->save();
        $this->syncLines($to, $items, $payments);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $payments
     */
    private function applyTotals(Quotation $quotation, array $items, array $payments): void
    {
        $totals = $this->totals($quotation, $items);

        $quotation->subtotal = $totals->subtotal;
        $quotation->discount_amount = $totals->discountAmount;
        $quotation->taxable_amount = $totals->taxable;
        $quotation->vat_amount = $totals->vat;
        $quotation->total = $totals->total;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @param  list<array<string, mixed>>  $payments
     */
    private function syncLines(Quotation $quotation, array $items, array $payments): void
    {
        $totals = $this->totals($quotation, $items);

        $quotation->items()->delete();
        foreach ($items as $index => $item) {
            $quotation->items()->create([
                'section_ar' => $item['section_ar'] ?? null,
                'section_en' => $item['section_en'] ?? null,
                'price_item_id' => $item['price_item_id'] ?? null,
                'description_ar' => $item['description_ar'],
                'description_en' => $item['description_en'] ?? null,
                'unit_id' => $item['unit_id'] ?? null,
                'quantity' => (string) $item['quantity'],
                'unit_price' => (string) $item['unit_price'],
                'line_total' => $totals->lineTotals[$index],
                'sort_order' => $index + 1,
            ]);
        }

        $amounts = $totals->paymentAmounts(array_map(fn (array $row) => (string) $row['percent'], $payments));

        $quotation->payments()->delete();
        foreach ($payments as $index => $payment) {
            $quotation->payments()->create([
                'label_ar' => $payment['label_ar'],
                'label_en' => $payment['label_en'] ?? null,
                'percent' => (string) $payment['percent'],
                'amount' => $amounts[$index],
                'sort_order' => $index + 1,
            ]);
        }

        $quotation->unsetRelation('items')->unsetRelation('payments');
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function totals(Quotation $quotation, array $items): QuotationTotals
    {
        return $this->calculator->calculate(
            array_map(fn (array $item) => ['quantity' => (string) $item['quantity'], 'unit_price' => (string) $item['unit_price']], $items),
            $quotation->discount_type instanceof DiscountType ? $quotation->discount_type : DiscountType::tryFrom((string) $quotation->discount_type),
            (string) ($quotation->discount_value ?? '0'),
            (string) $quotation->vat_rate,
            $this->finance->decimals,
        );
    }
}
