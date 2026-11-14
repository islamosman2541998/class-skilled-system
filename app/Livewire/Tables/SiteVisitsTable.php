<?php

namespace App\Livewire\Tables;

use App\Enums\LookupType;
use App\Enums\SiteVisitStatus;
use App\Livewire\DataTable;
use App\Models\Lookup;
use App\Models\SiteVisit;
use App\Models\User;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class SiteVisitsTable extends DataTable
{
    #[Locked]
    public ?int $clientId = null;

    public function tableKey(): string
    {
        return $this->clientId ? 'site_visits.client' : 'site_visits';
    }

    protected function permissionPrefix(): ?string
    {
        return 'site_visits';
    }

    protected function query(): Builder
    {
        return SiteVisit::query()
            ->with(['client', 'engineer'])
            ->when($this->clientId, fn (Builder $query) => $query->where('client_id', $this->clientId));
    }

    protected function columns(): array
    {
        return [
            Column::make('number', 'visits.number')->sortable()->view('site-visits.partials.number')->exportAs(fn (SiteVisit $row) => $row->number)->onCard(),
            Column::make('client', 'visits.client')->format(fn (SiteVisit $row) => $row->client->name)->onCard(),
            Column::make('scheduled_at', 'visits.scheduled_at')->sortable()->format(fn (SiteVisit $row) => fmt_datetime($row->scheduled_at))->onCard(),
            Column::make('engineer_id', 'visits.engineer')->format(fn (SiteVisit $row) => $row->engineer->name),
            Column::make('status', 'visits.status')->sortable()->view('site-visits.partials.status')->exportAs(fn (SiteVisit $row) => $row->status->label())->onCard(),
            Column::make('address', 'visits.address')->format(fn (SiteVisit $row) => (string) $row->address)->hiddenByDefault(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('status', 'visits.status')
                ->options(fn () => collect(SiteVisitStatus::cases())->mapWithKeys(fn (SiteVisitStatus $status) => [$status->value => $status->label()])),
            SelectFilter::make('engineer_id', 'visits.engineer')->options(fn () => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            SelectFilter::make('service', 'visits.service')
                ->options(fn () => Lookup::options(LookupType::Service)->pluck('name', 'id'))
                ->query(fn (Builder $query, string $value) => $query->whereHas('lead', fn (Builder $lead) => $lead->where('service_id', $value))),
        ];
    }

    protected function searchColumns(): array
    {
        return ['number', 'address', 'client.name_ar', 'client.name_en'];
    }

    protected function dateColumn(): ?string
    {
        return 'scheduled_at';
    }

    protected function defaultSort(): array
    {
        return ['scheduled_at', 'desc'];
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('site_visits.update') ? route('site-visits.edit', $row) : null;
    }
}
