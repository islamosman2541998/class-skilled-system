<?php

namespace App\Actions\Resources;

use App\Enums\SubcontractStatus;
use App\Models\Purchase;
use App\Models\SubcontractPayment;
use App\Models\User;
use DomainException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * "Bill" on a subcontract payment line (spec §8.16): one purchase invoice for the line, from the
 * subcontractor on the subcontract's project. A second call (double click, two tabs) returns it.
 */
class BillSubcontractPayment
{
    public function __construct(private readonly SavePurchase $save) {}

    /**
     * @throws DomainException with a translated message when the subcontract is cancelled
     */
    public function handle(SubcontractPayment $payment, User $by): Purchase
    {
        try {
            return DB::transaction(function () use ($payment, $by) {
                $locked = SubcontractPayment::query()->with('subcontract')->lockForUpdate()->findOrFail($payment->id);

                if (($existing = Purchase::query()->where('subcontract_payment_id', $locked->id)->first()) !== null) {
                    return $existing;
                }

                $subcontract = $locked->subcontract;

                if ($subcontract->status === SubcontractStatus::Cancelled) {
                    throw new DomainException(__('resources.subcontracts.cancelled_locked'));
                }

                return $this->save->handle([
                    'supplier_id' => $subcontract->supplier_id,
                    'project_id' => $subcontract->project_id,
                    'purchase_date' => today()->toDateString(),
                    'supplier_invoice_no' => $subcontract->number.'/'.$locked->sort_order,
                    'vat' => (float) $subcontract->vat_rate > 0,
                    'items' => [[
                        'description_ar' => $locked->label_ar.' - '.$subcontract->number,
                        'description_en' => filled($locked->label_en) ? $locked->label_en.' - '.$subcontract->number : null,
                        'quantity' => '1',
                        'unit_price' => (string) $locked->amount,
                    ]],
                ], $by, null, ['subcontract_id' => $subcontract->id, 'subcontract_payment_id' => $locked->id]);
            });
        } catch (UniqueConstraintViolationException) {
            // Another request billed the same line at the same moment.
            return Purchase::query()->where('subcontract_payment_id', $payment->id)->firstOrFail();
        }
    }
}
