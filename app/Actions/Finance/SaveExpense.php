<?php

namespace App\Actions\Finance;

use App\Actions\AttachUploads;
use App\Enums\DocumentType;
use App\Models\Expense;
use App\Models\User;
use App\Services\Finance\ExpenseAmounts;
use App\Services\NumberGenerator;
use App\Settings\FinanceSettings;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates an expense; the VAT figures are computed here from the typed amount and
 * mode, with the VAT rate of the settings at the time (spec §5.9, §8.12).
 */
class SaveExpense
{
    public function __construct(
        private readonly NumberGenerator $numbers,
        private readonly FinanceSettings $finance,
        private readonly AttachUploads $attach,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated data with "amount" and "vat_mode"
     */
    public function handle(array $data, User $by, ?Expense $expense = null): Expense
    {
        return DB::transaction(function () use ($data, $by, $expense) {
            $creating = $expense === null;
            $expense ??= new Expense;

            $expense->fill([
                'project_id' => $data['project_id'] ?? null,
                'category_id' => $data['category_id'],
                'supplier_name' => $data['supplier_name'] ?? null,
                'expense_date' => $data['expense_date'] ?? today(),
                'payment_method_id' => $data['payment_method_id'] ?? null,
                'paid_by' => $data['paid_by'] ?? $by->id,
                'supplier_invoice_no' => $data['supplier_invoice_no'] ?? null,
                'description' => $data['description'] ?? null,
            ]);

            // An edit keeps the VAT rate of the expense unless the user switches VAT off or on.
            $rate = ! $creating && (float) $expense->vat_rate > 0 ? (string) $expense->vat_rate : $this->finance->vat_rate;
            $expense->forceFill(ExpenseAmounts::compute((string) $data['amount'], (string) $data['vat_mode'], $rate, $this->finance->decimals));

            if ($creating) {
                $expense->number = $this->numbers->next(DocumentType::Expense, $expense->expense_date);
                $expense->created_by = $by->id;
            }

            $expense->save();

            if (! empty($data['uploads'])) {
                $this->attach->handle($expense, $data['uploads'], $by);
            }

            return $expense;
        });
    }
}
