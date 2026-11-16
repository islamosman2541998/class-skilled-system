<?php

namespace App\Livewire\Tables;

use App\Enums\EquipmentOwnership;
use App\Enums\LookupType;
use App\Livewire\DataTable;
use App\Models\Equipment;
use App\Models\Lookup;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Equipment (spec §8.17): filters type, ownership and active. */
class EquipmentTable extends DataTable
{
    public function tableKey(): string
    {
        return 'equipment';
    }

    protected function permissionPrefix(): ?string
    {
        return 'equipment';
    }

    protected function query(): Builder
    {
        return Equipment::query()->with('type');
    }

    protected function columns(): array
    {
        return [
            Column::make('name_ar', 'resources.equipment.name')->sortable()->view('equipment.partials.name')->exportAs(fn (Equipment $row) => $row->name)->onCard(),
            Column::make('type', 'resources.equipment.type')->format(fn (Equipment $row) => (string) $row->type?->name)->onCard(),
            Column::make('ownership', 'resources.equipment.ownership')->sortable()->format(fn (Equipment $row) => $row->ownership->label()),
            Column::make('daily_cost', 'resources.equipment.daily_cost')->sortable()->alignEnd()->format(fn (Equipment $row) => money($row->daily_cost))->exportAs(fn (Equipment $row) => $row->daily_cost)->onCard(),
            Column::make('is_active', 'resources.suppliers.active')->sortable()->format(fn (Equipment $row) => __('resources.suppliers.'.($row->is_active ? 'is_active' : 'is_inactive'))),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('type_id', 'resources.equipment.type')->options(fn () => Lookup::options(LookupType::EquipmentType)->pluck('name', 'id')),
            SelectFilter::make('ownership', 'resources.equipment.ownership')
                ->options(fn () => collect(EquipmentOwnership::cases())->mapWithKeys(fn (EquipmentOwnership $ownership) => [$ownership->value => $ownership->label()])),
            BooleanFilter::make('is_active', 'resources.suppliers.active'),
        ];
    }

    protected function searchColumns(): array
    {
        return ['name_ar', 'name_en'];
    }

    protected function defaultSort(): array
    {
        return ['name_ar', 'asc'];
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('equipment.update') ? route('equipment.edit', $row) : null;
    }
}
