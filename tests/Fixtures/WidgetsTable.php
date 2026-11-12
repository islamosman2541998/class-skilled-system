<?php

namespace Tests\Fixtures;

use App\Livewire\DataTable;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\MultiSelectFilter;
use App\Tables\Filters\NumberRangeFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

class WidgetsTable extends DataTable
{
    public function tableKey(): string
    {
        return 'widgets';
    }

    protected function permissionPrefix(): ?string
    {
        return 'widgets';
    }

    protected function query(): Builder
    {
        return Widget::query();
    }

    protected function columns(): array
    {
        return [
            Column::make('name', 'table.name')->sortable('name_ar')->format(fn (Widget $row) => $row->name_ar)->onCard(),
            Column::make('status', 'table.status')->sortable()->onCard(),
            Column::make('amount', 'table.amount')->sortable()->summable()->alignEnd()->format(fn (Widget $row) => money($row->amount, false)),
            Column::make('issued_on', 'table.date')->sortable()->format(fn (Widget $row) => fmt_date($row->issued_on)),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('status', 'table.status')->options(['open' => 'Open', 'closed' => 'Closed']),
            MultiSelectFilter::make('statuses', 'table.status')->column('status')->options(['open' => 'Open', 'closed' => 'Closed', 'draft' => 'Draft']),
            BooleanFilter::make('featured', 'table.status')->column('is_featured'),
            NumberRangeFilter::make('amount', 'table.amount'),
        ];
    }

    protected function searchColumns(): array
    {
        return ['name_ar', 'name_en'];
    }

    protected function dateColumn(): ?string
    {
        return 'issued_on';
    }
}
