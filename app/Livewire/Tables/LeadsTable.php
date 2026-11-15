<?php

namespace App\Livewire\Tables;

use App\Enums\LookupType;
use App\Livewire\DataTable;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\User;
use App\Support\Phone;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class LeadsTable extends DataTable
{
    /** Set on the client page to list that client's requests only. */
    #[Locked]
    public ?int $clientId = null;

    public function tableKey(): string
    {
        return $this->clientId ? 'leads.client' : 'leads';
    }

    protected function permissionPrefix(): ?string
    {
        return 'leads';
    }

    protected function query(): Builder
    {
        return Lead::query()
            ->with(['client', 'status', 'service', 'assignee'])
            ->when($this->clientId, fn (Builder $query) => $query->where('client_id', $this->clientId));
    }

    protected function columns(): array
    {
        return [
            Column::make('number', 'leads.number')->sortable()->view('leads.partials.number')->exportAs(fn (Lead $row) => $row->number)->onCard(),
            Column::make('contact', 'leads.contact')->format(fn (Lead $row) => $row->displayName())->onCard(),
            Column::make('phone', 'app.phone')->view('leads.partials.phone')->exportAs(fn (Lead $row) => (string) ($row->contact_phone ?? $row->client?->phone)),
            Column::make('service', 'leads.service')->format(fn (Lead $row) => (string) $row->service?->name),
            Column::make('status', 'leads.status')->view('leads.partials.status')->exportAs(fn (Lead $row) => $row->status->name)->onCard(),
            Column::make('assigned_to', 'leads.assigned_to')->format(fn (Lead $row) => (string) $row->assignee?->name),
            Column::make('follow_up_at', 'leads.follow_up_at')->sortable()->view('leads.partials.follow-up')
                ->exportAs(fn (Lead $row) => $row->follow_up_at ? fmt_datetime($row->follow_up_at) : ''),
            Column::make('created_at', 'table.created_at')->sortable()->format(fn (Lead $row) => fmt_date($row->created_at)),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('status_id', 'leads.status')->options(fn () => Lookup::options(LookupType::LeadStatus)->pluck('name', 'id')),
            SelectFilter::make('service_id', 'leads.service')->options(fn () => Lookup::options(LookupType::Service)->pluck('name', 'id')),
            SelectFilter::make('source_id', 'leads.source')->options(fn () => Lookup::options(LookupType::LeadSource)->pluck('name', 'id')),
            SelectFilter::make('assigned_to', 'leads.assigned_to')->options(fn () => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            SelectFilter::make('emirate_id', 'leads.emirate')->options(fn () => Lookup::options(LookupType::Emirate)->pluck('name', 'id')),
            BooleanFilter::make('overdue', 'leads.overdue')->query(function (Builder $query, mixed $value) {
                if ((string) $value === '1') {
                    Lead::constrainOverdueFollowUp($query);
                }
            }),
            BooleanFilter::make('follow_up_today', 'leads.follow_up_today')->query(function (Builder $query, mixed $value) {
                if ((string) $value === '1') {
                    Lead::constrainFollowUpToday($query);
                }
            }),
        ];
    }

    /** The request date (spec §8.2). */
    protected function dateColumn(): ?string
    {
        return 'created_at';
    }

    protected function searchColumns(): array
    {
        return ['number', 'contact_name', 'contact_phone', 'contact_email', 'client.name_ar', 'client.name_en'];
    }

    protected function searchVariants(string $term): array
    {
        return array_map(fn (string $digits) => [['contact_phone_normalized', 'client.phone_normalized'], $digits], Phone::searchVariants($term));
    }

    protected function editUrl(Model $row): ?string
    {
        return auth()->user()?->can('leads.update') ? route('leads.edit', $row) : null;
    }
}
