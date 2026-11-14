<?php

namespace App\Actions\Finance;

use App\Enums\DocumentType;
use App\Enums\ReceiptStatus;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\User;
use App\Services\Finance\ProjectLedger;
use App\Services\NumberGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a receipt (spec §8.11). The client and project are fixed once created; the
 * amount can change but never below what is already allocated.
 */
class SaveReceipt
{
    public function __construct(private readonly NumberGenerator $numbers) {}

    /**
     * @param  array<string, mixed>  $data  validated SaveReceiptRequest data
     */
    public function handle(array $data, User $by, ?Receipt $receipt = null): Receipt
    {
        return DB::transaction(function () use ($data, $by, $receipt) {
            $creating = $receipt === null;
            $receipt = $creating ? new Receipt : Receipt::query()->lockForUpdate()->findOrFail($receipt->id);

            $receipt->fill([
                'receipt_date' => $data['receipt_date'],
                'amount' => (string) $data['amount'],
                'payment_method_id' => $data['payment_method_id'],
                'reference' => $data['reference'] ?? null,
                'cheque_date' => $data['cheque_date'] ?? null,
                'received_by' => $data['received_by'],
                'notes' => $data['notes'] ?? null,
            ]);

            if ($creating) {
                $receipt->client_id = (int) $data['client_id'];
                $receipt->project_id = isset($data['project_id']) ? (int) $data['project_id'] : null;
                $receipt->number = $this->numbers->next(DocumentType::Receipt, $receipt->receipt_date);
                $receipt->status = ReceiptStatus::Active;
                $receipt->created_by = $by->id;
            } elseif (bccomp((string) $receipt->amount, $receipt->allocated(), 2) < 0) {
                throw ValidationException::withMessages(['amount' => __('finance.receipts.amount_below_allocated', ['amount' => money($receipt->allocated())])]);
            }

            $receipt->save();

            if ($receipt->project_id !== null) {
                ProjectLedger::refresh(Project::query()->findOrFail($receipt->project_id));
            }

            return $receipt;
        });
    }
}
