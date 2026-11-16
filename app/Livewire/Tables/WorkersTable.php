<?php

namespace App\Livewire\Tables;

use App\Enums\LookupType;
use App\Livewire\DataTable;
use App\Models\Lookup;
use App\Models\Worker;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Workers (spec §8.17): filters trade, company or external, and active. */
class WorkersTable extends DataTable
{
    public function tableKey(): string
    {
        return 'workers';
    }

    protected function permissionPrefix(): ?string
    {
        return 'workers';
    }

    protected function query(): Builder
    {
        return Worker::query()->with('trade');
    }

    protected function columns(): array
    {
        return [
            Column::make('name_ar', 'resources.workers.name')->sortable()->format(fn (Worker $row) => $row->name)->onCard(),
            Column::make('trade', 'resources.workers.trade')->format(fn (Worker $row) => (string) $row->trade?->name)->onCard(),
            Column::make('daily_wage', 'resources.workers.daily_wage')->sortable()->alignEnd()->format(fn (Worker $row) => money($row->daily_wage))->exportAs(fn (Worker $row) => $row->daily_wage)->onCard(),
            Column::make('is_company', 'resources.workers.employment')->sortable()->format(fn (Worker $row) => __('resources.workers.'.($row->is_company ? 'company' : 'external'))),
            Column::make('phone', 'app.phone')->format(fn (Worker $row) => (string) $row->phone)->hiddenByDefault(),
            Column::make('is_active', 'resources.suppliers.active')->sortable()->format(fn (Worker $row) => __('resources.suppliers.'.($row->is_active ? 'is_active' : 'is_inactive'))),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('trade_id', 'resources.workers.trade')->options(fn () => Lookup::options(LookupType::WorkerTrade)->pluck('name', 'id')),
            BooleanFilter::make('is_company', 'resources.workers.company'),
            BooleanFilter::make('is_active', 'resources.suppliers.active'),
        ];
    }

    protected function searchColumns(): array
    {
        return ['name_ar', 'name_en', 'phone'];
    }

    protected function defaultSort(): array
    {
        return ['name_ar', 'asc'];
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('workers.update') ? route('workers.edit', $row) : null;
    }
}
