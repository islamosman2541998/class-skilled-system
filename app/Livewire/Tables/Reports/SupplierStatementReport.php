<?php

namespace App\Livewire\Tables\Reports;

use App\Models\ReportRow;
use App\Models\Supplier;
use App\Services\Resources\SupplierStatement;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Supplier statement of account (spec §9.2): opening balance, purchase invoices and active payment
 * vouchers in date order with the balance owed after each line. Empty until a supplier is chosen.
 */
class SupplierStatementReport extends ReportTable
{
    public function tableKey(): string
    {
        return 'reports.supplier-statement';
    }

    protected function aggregate(): QueryBuilder
    {
        [$from, $to] = $this->period();

        return SupplierStatement::query($this->supplierId(), auth()->user(), $from, $to);
    }

    private function supplierId(): ?int
    {
        $id = (int) ($this->filters['supplier_id'] ?? 0);

        return $id > 0 && Supplier::query()->whereKey($id)->exists() ? $id : null;
    }

    protected function columns(): array
    {
        $money = fn (string $key, string $label) => Column::make($key, $label)->alignEnd()
            ->format(fn (ReportRow $row) => bccomp((string) $row->getAttribute($key), '0', 2) === 0 ? '' : money((string) $row->getAttribute($key)))
            ->exportAs(fn (ReportRow $row) => $row->getAttribute($key));

        return [
            Column::make('entry_date', 'reports.date')->format(fn (ReportRow $row) => implode('/', array_reverse(explode('-', substr((string) $row->getAttribute('entry_date'), 0, 10)))))->onCard(),
            Column::make('document', 'reports.statement_document')->view('reports.partials.supplier-statement-document')
                ->exportAs(fn (ReportRow $row) => trim(__('resources.statement.entries.'.$row->getAttribute('entry')).' '.$row->getAttribute('number')))->onCard(),
            $money('debit', 'resources.statement.paid')->summable(),
            $money('credit', 'resources.statement.purchased')->summable(),
            Column::make('balance', 'resources.statement.balance')->alignEnd()
                ->format(fn (ReportRow $row) => money((string) $row->getAttribute('balance')))->exportAs(fn (ReportRow $row) => $row->getAttribute('balance'))->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            // Read by aggregate(); the statement is always of one supplier.
            SelectFilter::make('supplier_id', 'resources.purchases.supplier')
                ->options(fn () => Supplier::query()->orderBy('name_ar')->get()->mapWithKeys(fn (Supplier $supplier) => [$supplier->id => $supplier->name]))
                ->query(fn (Builder $query) => null),
        ];
    }

    protected function dateColumn(): ?string
    {
        return 'entry_date';
    }

    /** Lines keep date order so the running balance reads top to bottom. */
    protected function defaultSort(): array
    {
        return ['id', 'asc'];
    }
}
