<?php

namespace App\Livewire\Tables;

use App\Enums\ClientType;
use App\Enums\LookupType;
use App\Livewire\DataTable;
use App\Models\Client;
use App\Models\Lookup;
use App\Support\Phone;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ClientsTable extends DataTable
{
    public function tableKey(): string
    {
        return 'clients';
    }

    protected function permissionPrefix(): ?string
    {
        return 'clients';
    }

    protected function query(): Builder
    {
        return Client::query()->with('emirate');
    }

    protected function columns(): array
    {
        return [
            Column::make('name_ar', 'clients.name')->sortable()->view('clients.partials.name')
                ->exportAs(fn (Client $row) => $row->name)->onCard(),
            Column::make('type', 'clients.type')->sortable()->format(fn (Client $row) => $row->type->label())->onCard(),
            Column::make('phone', 'app.phone')->view('clients.partials.phone')->exportAs(fn (Client $row) => $row->phone)->onCard(),
            Column::make('email', 'app.email'),
            Column::make('emirate', 'clients.emirate')->format(fn (Client $row) => (string) $row->emirate?->name),
            Column::make('created_at', 'table.created_at')->sortable()->format(fn (Client $row) => fmt_date($row->created_at)),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('type', 'clients.type')
                ->options(fn () => collect(ClientType::cases())->mapWithKeys(fn (ClientType $type) => [$type->value => $type->label()])),
            SelectFilter::make('source_id', 'clients.source')->options(fn () => Lookup::options(LookupType::LeadSource)->pluck('name', 'id')),
            SelectFilter::make('emirate_id', 'clients.emirate')->options(fn () => Lookup::options(LookupType::Emirate)->pluck('name', 'id')),
        ];
    }

    protected function searchColumns(): array
    {
        return ['name_ar', 'name_en', 'email', 'trn', 'phone', 'phone2'];
    }

    protected function searchVariants(string $term): array
    {
        return array_map(fn (string $digits) => [['phone_normalized'], $digits], Phone::searchVariants($term));
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('clients.update') ? route('clients.edit', $row) : null;
    }
}
