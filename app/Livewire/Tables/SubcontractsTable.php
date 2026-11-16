<?php

namespace App\Livewire\Tables;

use App\Enums\SubcontractStatus;
use App\Enums\SupplierType;
use App\Livewire\DataTable;
use App\Models\Project;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

/**
 * Subcontracts (spec §8.16): filters status, project and subcontractor.
 */
class SubcontractsTable extends DataTable
{
    #[Locked]
    public ?int $projectId = null;

    #[Locked]
    public ?int $supplierId = null;

    public function tableKey(): string
    {
        return match (true) {
            $this->projectId !== null => 'subcontracts.project',
            $this->supplierId !== null => 'subcontracts.supplier',
            default => 'subcontracts',
        };
    }

    protected function permissionPrefix(): ?string
    {
        return 'subcontracts';
    }

    protected function query(): Builder
    {
        return Subcontract::query()
            ->with(['supplier', 'project'])
            ->withSum('purchases as billed_total', 'subtotal')
            ->when($this->projectId, fn (Builder $query) => $query->where('project_id', $this->projectId))
            ->when($this->supplierId, fn (Builder $query) => $query->where('supplier_id', $this->supplierId));
    }

    protected function columns(): array
    {
        return [
            Column::make('number', 'resources.subcontracts.number')->sortable()->view('subcontracts.partials.number')->exportAs(fn (Subcontract $row) => $row->number)->onCard(),
            Column::make('supplier', 'resources.subcontracts.subcontractor')->format(fn (Subcontract $row) => $row->supplier->name)->onCard(),
            Column::make('project', 'resources.subcontracts.project')->format(fn (Subcontract $row) => $row->project->number.' · '.$row->project->title),
            Column::make('value', 'resources.subcontracts.value')->sortable()->summable()->alignEnd()->format(fn (Subcontract $row) => money($row->value))->exportAs(fn (Subcontract $row) => $row->value)->onCard(),
            Column::make('billed_total', 'resources.subcontracts.billed_total')->alignEnd()
                ->format(fn (Subcontract $row) => money((string) ($row->getAttribute('billed_total') ?? '0')))->exportAs(fn (Subcontract $row) => $row->getAttribute('billed_total') ?? '0'),
            Column::make('status', 'resources.subcontracts.status')->sortable()->view('subcontracts.partials.status')->exportAs(fn (Subcontract $row) => $row->status->label())->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return array_values(array_filter([
            SelectFilter::make('status', 'resources.subcontracts.status')
                ->options(fn () => collect(SubcontractStatus::cases())->mapWithKeys(fn (SubcontractStatus $status) => [$status->value => $status->label()])),
            $this->projectId === null ? SelectFilter::make('project_id', 'resources.subcontracts.project')
                ->options(fn () => Project::query()->visibleTo(auth()->user())->latest('id')->get()->mapWithKeys(fn (Project $project) => [$project->id => $project->number.' · '.$project->title])) : null,
            $this->supplierId === null ? SelectFilter::make('supplier_id', 'resources.subcontracts.subcontractor')
                ->options(fn () => Supplier::query()->where('type', SupplierType::Subcontractor->value)->orderBy('name_ar')->get()->mapWithKeys(fn (Supplier $supplier) => [$supplier->id => $supplier->name])) : null,
        ]));
    }

    protected function searchColumns(): array
    {
        return ['number', 'scope', 'supplier.name_ar', 'supplier.name_en', 'project.number', 'project.title_ar'];
    }

    protected function dateColumn(): ?string
    {
        return 'start_date';
    }

    protected function defaultSort(): array
    {
        return ['start_date', 'desc'];
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('subcontracts.update') ? route('subcontracts.edit', $row) : null;
    }
}
