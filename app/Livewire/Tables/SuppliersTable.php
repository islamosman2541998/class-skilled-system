<?php

namespace App\Livewire\Tables;

use App\Enums\LookupType;
use App\Enums\SupplierType;
use App\Livewire\DataTable;
use App\Models\Lookup;
use App\Models\Supplier;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Suppliers and subcontractors (spec §8.15): filters type, specialty and active.
 */
class SuppliersTable extends DataTable
{
    public function tableKey(): string
    {
        return 'suppliers';
    }

    protected function permissionPrefix(): ?string
    {
        return 'suppliers';
    }

    protected function query(): Builder
    {
        return Supplier::query()->with('specialty');
    }

    protected function columns(): array
    {
        return [
            Column::make('name_ar', 'resources.suppliers.name')->sortable()->view('suppliers.partials.name')->exportAs(fn (Supplier $row) => $row->name)->onCard(),
            Column::make('type', 'resources.suppliers.type')->sortable()->view('suppliers.partials.type')->exportAs(fn (Supplier $row) => $row->type->label())->onCard(),
            Column::make('specialty', 'resources.suppliers.specialty')->format(fn (Supplier $row) => (string) $row->specialty?->name),
            Column::make('phone', 'app.phone')->format(fn (Supplier $row) => (string) $row->phone)->onCard(),
            Column::make('trn', 'resources.suppliers.trn')->hiddenByDefault(),
            Column::make('is_active', 'resources.suppliers.active')->sortable()->format(fn (Supplier $row) => __('resources.suppliers.'.($row->is_active ? 'is_active' : 'is_inactive'))),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('type', 'resources.suppliers.type')
                ->options(fn () => collect(SupplierType::cases())->mapWithKeys(fn (SupplierType $type) => [$type->value => $type->label()])),
            SelectFilter::make('specialty_id', 'resources.suppliers.specialty')->options(fn () => Lookup::options(LookupType::SupplierSpecialty)->pluck('name', 'id')),
            BooleanFilter::make('is_active', 'resources.suppliers.active'),
        ];
    }

    protected function searchColumns(): array
    {
        return ['name_ar', 'name_en', 'phone', 'email', 'trn'];
    }

    protected function defaultSort(): array
    {
        return ['name_ar', 'asc'];
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('suppliers.update') ? route('suppliers.edit', $row) : null;
    }
}
