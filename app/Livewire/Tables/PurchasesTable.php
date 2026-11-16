<?php

namespace App\Livewire\Tables;

use App\Enums\PurchaseStatus;
use App\Livewire\DataTable;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

/**
 * Purchase invoices (spec §8.16): filters supplier, project, status and the invoice date.
 */
class PurchasesTable extends DataTable
{
    #[Locked]
    public ?int $projectId = null;

    #[Locked]
    public ?int $supplierId = null;

    public function tableKey(): string
    {
        return match (true) {
            $this->projectId !== null => 'purchases.project',
            $this->supplierId !== null => 'purchases.supplier',
            default => 'purchases',
        };
    }

    protected function permissionPrefix(): ?string
    {
        return 'purchases';
    }

    protected function query(): Builder
    {
        return Purchase::query()
            ->with(['supplier', 'project'])
            ->when($this->projectId, fn (Builder $query) => $query->where('project_id', $this->projectId))
            ->when($this->supplierId, fn (Builder $query) => $query->where('supplier_id', $this->supplierId));
    }

    protected function columns(): array
    {
        $money = fn (string $key, string $label) => Column::make($key, $label)->sortable()->summable()->alignEnd()
            ->format(fn (Purchase $row) => money((string) $row->getAttribute($key)))->exportAs(fn (Purchase $row) => $row->getAttribute($key));

        return [
            Column::make('number', 'resources.purchases.number')->sortable()->view('purchases.partials.number')->exportAs(fn (Purchase $row) => $row->number)->onCard(),
            Column::make('supplier', 'resources.purchases.supplier')->format(fn (Purchase $row) => $row->supplier->name)->onCard(),
            Column::make('project', 'resources.purchases.project')->format(fn (Purchase $row) => $row->project->number.' · '.$row->project->title),
            Column::make('supplier_invoice_no', 'resources.purchases.supplier_invoice_no')->sortable(),
            Column::make('purchase_date', 'resources.purchases.date')->sortable()->format(fn (Purchase $row) => fmt_date($row->purchase_date))->onCard(),
            $money('subtotal', 'resources.purchases.subtotal')->hiddenByDefault(),
            $money('total', 'resources.purchases.total')->onCard(),
            $money('paid_amount', 'resources.purchases.paid'),
            Column::make('status', 'resources.purchases.status')->sortable()->view('purchases.partials.status')->exportAs(fn (Purchase $row) => $row->status->label())->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return array_values(array_filter([
            $this->supplierId === null ? SelectFilter::make('supplier_id', 'resources.purchases.supplier')
                ->options(fn () => Supplier::query()->orderBy('name_ar')->get()->mapWithKeys(fn (Supplier $supplier) => [$supplier->id => $supplier->name])) : null,
            $this->projectId === null ? SelectFilter::make('project_id', 'resources.purchases.project')
                ->options(fn () => Project::query()->visibleTo(auth()->user())->latest('id')->get()->mapWithKeys(fn (Project $project) => [$project->id => $project->number.' · '.$project->title])) : null,
            SelectFilter::make('status', 'resources.purchases.status')
                ->options(fn () => collect(PurchaseStatus::cases())->mapWithKeys(fn (PurchaseStatus $status) => [$status->value => $status->label()])),
        ]));
    }

    protected function searchColumns(): array
    {
        return ['number', 'supplier_invoice_no', 'supplier.name_ar', 'supplier.name_en', 'project.number', 'project.title_ar'];
    }

    protected function dateColumn(): ?string
    {
        return 'purchase_date';
    }

    protected function defaultSort(): array
    {
        return ['purchase_date', 'desc'];
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('purchases.update') ? route('purchases.edit', $row) : null;
    }
}
