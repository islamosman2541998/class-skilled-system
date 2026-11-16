<?php

namespace App\Actions\Resources;

use App\Actions\AttachUploads;
use App\Enums\DocumentType;
use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\User;
use App\Services\Finance\InvoiceCalculator;
use App\Services\NumberGenerator;
use App\Settings\FinanceSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a purchase invoice (spec §8.16): lines and totals on the server, VAT at the
 * settings rate or none for a supplier without VAT. Once a voucher pays it, the supplier and the
 * project stay as they are and the total cannot drop below what is paid.
 */
class SavePurchase
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly FinanceSettings $finance,
        private readonly AttachUploads $attach,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated SavePurchaseRequest data
     * @param  array<string, mixed>  $system  links set by the system (subcontract_id, subcontract_payment_id)
     */
    public function handle(array $data, User $by, ?Purchase $purchase = null, array $system = []): Purchase
    {
        return DB::transaction(function () use ($data, $by, $purchase, $system) {
            $creating = $purchase === null;
            $purchase = $creating ? new Purchase : Purchase::query()->lockForUpdate()->findOrFail($purchase->id);
            $paid = bccomp((string) ($purchase->paid_amount ?? '0'), '0', 2) > 0;

            if ($paid && ((int) $data['supplier_id'] !== $purchase->supplier_id || (int) $data['project_id'] !== $purchase->project_id)) {
                throw ValidationException::withMessages(['supplier_id' => __('resources.purchases.paid_fixed')]);
            }

            $purchase->fill([
                'purchase_date' => $data['purchase_date'],
                'supplier_invoice_no' => $data['supplier_invoice_no'],
                'notes' => $data['notes'] ?? null,
            ]);
            $purchase->supplier_id = (int) $data['supplier_id'];
            $purchase->project_id = (int) $data['project_id'];

            // An edit keeps the rate of the invoice unless VAT is switched off or on.
            $vat = filter_var($data['vat'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $purchase->vat_rate = ! $vat ? '0' : (! $creating && (float) $purchase->vat_rate > 0 ? (string) $purchase->vat_rate : $this->finance->vat_rate);

            $items = array_values($data['items']);
            $totals = InvoiceCalculator::fromLines($items, (string) $purchase->vat_rate, $this->finance->decimals);

            if ($paid && bccomp($totals->total, (string) $purchase->paid_amount, 2) < 0) {
                throw ValidationException::withMessages(['items' => __('resources.purchases.below_paid', ['amount' => money($purchase->paid_amount)])]);
            }

            $purchase->subtotal = $totals->subtotal;
            $purchase->vat_amount = $totals->vat;
            $purchase->total = $totals->total;

            if ($creating) {
                $purchase->number = $this->numbers->next(DocumentType::Purchase, $purchase->purchase_date);
                $purchase->paid_amount = '0';
                $purchase->status = PurchaseStatus::Unpaid;
                $purchase->created_by = $by->id;
                $purchase->subcontract_id = $system['subcontract_id'] ?? null;
                $purchase->subcontract_payment_id = $system['subcontract_payment_id'] ?? null;
            } else {
                $purchase->status = self::statusFor((string) $purchase->paid_amount, $totals->total);
            }

            $purchase->save();
            $purchase->items()->delete();

            foreach ($items as $index => $item) {
                $purchase->items()->create([
                    'description_ar' => $item['description_ar'],
                    'description_en' => $item['description_en'] ?? null,
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => (string) $item['quantity'],
                    'unit_price' => (string) $item['unit_price'],
                    'line_total' => $totals->lineTotals[$index],
                    'sort_order' => $index + 1,
                ]);
            }

            if (! empty($data['uploads'])) {
                $this->attach->handle($purchase, $data['uploads'], $by);
            }

            return $purchase;
        });
    }

    public static function statusFor(string $paid, string $total): PurchaseStatus
    {
        return match (true) {
            bccomp($paid, '0', 2) <= 0 => PurchaseStatus::Unpaid,
            bccomp($paid, $total, 2) >= 0 => PurchaseStatus::Paid,
            default => PurchaseStatus::Partial,
        };
    }
}
