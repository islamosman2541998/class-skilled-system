<?php

namespace App\Actions\Resources;

use App\Enums\DocumentType;
use App\Enums\SubcontractStatus;
use App\Models\Subcontract;
use App\Models\User;
use App\Services\NumberGenerator;
use App\Settings\FinanceSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a subcontract and its payment lines (spec §8.16). The lines never add up to
 * more than the value, and a billed line keeps its amount and cannot be removed.
 */
class SaveSubcontract
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly FinanceSettings $finance,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated SaveSubcontractRequest data
     *
     * @throws ValidationException on "payments" when a rule above is broken
     */
    public function handle(array $data, User $by, ?Subcontract $subcontract = null): Subcontract
    {
        return DB::transaction(function () use ($data, $by, $subcontract) {
            $creating = $subcontract === null;
            $subcontract = $creating ? new Subcontract : Subcontract::query()->lockForUpdate()->findOrFail($subcontract->id);

            $lines = array_values((array) ($data['payments'] ?? []));
            $sum = array_reduce($lines, fn (string $carry, array $line) => bcadd($carry, (string) $line['amount'], 2), '0');

            if (bccomp($sum, (string) $data['value'], 2) > 0) {
                throw ValidationException::withMessages(['payments' => __('resources.subcontracts.above_value', ['amount' => money($sum)])]);
            }

            $billed = $creating ? collect() : $subcontract->payments()->has('purchase')->get();

            foreach ($billed as $payment) {
                $line = collect($lines)->firstWhere('id', $payment->id);

                if ($line === null || bccomp((string) $line['amount'], (string) $payment->amount, 2) !== 0) {
                    throw ValidationException::withMessages(['payments' => __('resources.subcontracts.billed_locked')]);
                }
            }

            $subcontract->fill([
                'scope' => $data['scope'],
                'value' => (string) $data['value'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $subcontract->supplier_id = (int) $data['supplier_id'];
            $subcontract->project_id = (int) $data['project_id'];
            $subcontract->status = SubcontractStatus::tryFrom((string) ($data['status'] ?? '')) ?? ($subcontract->status ?? SubcontractStatus::Active);

            $vat = filter_var($data['vat'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $subcontract->vat_rate = ! $vat ? '0' : (! $creating && (float) $subcontract->vat_rate > 0 ? (string) $subcontract->vat_rate : $this->finance->vat_rate);

            if ($creating) {
                $subcontract->number = $this->numbers->next(DocumentType::Subcontract, $subcontract->start_date);
                $subcontract->created_by = $by->id;
            }

            $subcontract->save();

            $keep = [];

            foreach ($lines as $index => $line) {
                $payment = isset($line['id']) ? $subcontract->payments()->whereKey((int) $line['id'])->first() : null;
                $payment ??= $subcontract->payments()->make();
                $payment->fill([
                    'label_ar' => $line['label_ar'],
                    'label_en' => $line['label_en'] ?? null,
                    'amount' => (string) $line['amount'],
                    'due_date' => $line['due_date'] ?? null,
                    'sort_order' => $index + 1,
                ])->save();
                $keep[] = $payment->id;
            }

            $subcontract->payments()->whereNotIn('id', $keep)->delete();

            return $subcontract;
        });
    }
}
