<?php

namespace App\Livewire\Tables;

use App\Enums\LookupType;
use App\Livewire\DataTable;
use App\Models\Expense;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\User;
use App\Tables\Column;
use App\Tables\Filters\NumberRangeFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class ExpensesTable extends DataTable
{
    #[Locked]
    public ?int $projectId = null;

    public function tableKey(): string
    {
        return $this->projectId !== null ? 'expenses.project' : 'expenses';
    }

    protected function permissionPrefix(): ?string
    {
        return 'expenses';
    }

    protected function query(): Builder
    {
        return Expense::query()
            ->with(['project', 'category', 'paymentMethod', 'payer'])
            ->when($this->projectId, fn (Builder $query) => $query->where('project_id', $this->projectId));
    }

    protected function columns(): array
    {
        return [
            Column::make('number', 'finance.number')->sortable()->view('expenses.partials.number')->exportAs(fn (Expense $row) => $row->number)->onCard(),
            Column::make('expense_date', 'finance.expenses.date')->sortable()->format(fn (Expense $row) => fmt_date($row->expense_date))->onCard(),
            Column::make('project', 'finance.project')->format(fn (Expense $row) => $row->project ? $row->project->number.' · '.$row->project->title : __('finance.expenses.general'))->onCard(),
            Column::make('category_id', 'finance.expenses.category')->format(fn (Expense $row) => $row->category->name)->onCard(),
            Column::make('supplier_name', 'finance.expenses.supplier')->format(fn (Expense $row) => (string) $row->supplier_name),
            Column::make('amount', 'finance.expenses.amount')->sortable()->summable()->alignEnd()->format(fn (Expense $row) => money($row->amount))->exportAs(fn (Expense $row) => $row->amount),
            Column::make('vat_amount', 'finance.vat')->sortable()->summable()->alignEnd()->format(fn (Expense $row) => money($row->vat_amount))->exportAs(fn (Expense $row) => $row->vat_amount)->hiddenByDefault(),
            Column::make('total', 'finance.total')->sortable()->summable()->alignEnd()->format(fn (Expense $row) => money($row->total))->exportAs(fn (Expense $row) => $row->total)->onCard(),
            Column::make('payment_method_id', 'finance.receipts.method')->format(fn (Expense $row) => (string) $row->paymentMethod?->name)->hiddenByDefault(),
            Column::make('paid_by', 'finance.expenses.paid_by')->format(fn (Expense $row) => (string) $row->payer?->name)->hiddenByDefault(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return array_values(array_filter([
            $this->projectId === null ? SelectFilter::make('project_id', 'finance.project')
                ->options(fn () => Project::query()->visibleTo(auth()->user())->latest('id')->get()->mapWithKeys(fn (Project $project) => [$project->id => $project->number.' · '.$project->title])) : null,
            SelectFilter::make('category_id', 'finance.expenses.category')->options(fn () => Lookup::options(LookupType::ExpenseCategory)->pluck('name', 'id')),
            SelectFilter::make('created_by', 'finance.expenses.entered_by')->options(fn () => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            SelectFilter::make('paid_by', 'finance.expenses.paid_by')->options(fn () => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            SelectFilter::make('payment_method_id', 'finance.receipts.method')->options(fn () => Lookup::options(LookupType::PaymentMethod)->pluck('name', 'id')),
            NumberRangeFilter::make('amount', 'finance.expenses.amount'),
        ]));
    }

    protected function searchColumns(): array
    {
        return ['number', 'supplier_name', 'supplier_invoice_no', 'description', 'project.number', 'project.title_ar'];
    }

    protected function dateColumn(): ?string
    {
        return 'expense_date';
    }

    protected function defaultSort(): array
    {
        return ['expense_date', 'desc'];
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('expenses.update') ? route('expenses.edit', $row) : null;
    }
}
