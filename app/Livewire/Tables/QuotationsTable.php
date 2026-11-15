<?php

namespace App\Livewire\Tables;

use App\Enums\LookupType;
use App\Enums\QuotationStatus;
use App\Livewire\DataTable;
use App\Models\Lookup;
use App\Models\Quotation;
use App\Models\User;
use App\Tables\Column;
use App\Tables\Filters\BooleanFilter;
use App\Tables\Filters\NumberRangeFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class QuotationsTable extends DataTable
{
    #[Locked]
    public ?int $clientId = null;

    public function tableKey(): string
    {
        return $this->clientId ? 'quotations.client' : 'quotations';
    }

    protected function permissionPrefix(): ?string
    {
        return 'quotations';
    }

    protected function query(): Builder
    {
        return Quotation::query()
            ->with(['client', 'service', 'assignee'])
            ->when($this->clientId, fn (Builder $query) => $query->where('client_id', $this->clientId));
    }

    protected function columns(): array
    {
        return [
            Column::make('number', 'quotations.number')->sortable()->view('quotations.partials.number')->exportAs(fn (Quotation $row) => $row->number)->onCard(),
            Column::make('client', 'quotations.client')->format(fn (Quotation $row) => $row->client->name)->onCard(),
            Column::make('title_ar', 'quotations.title')->sortable()->format(fn (Quotation $row) => $row->title),
            Column::make('issue_date', 'quotations.issue_date')->sortable()->format(fn (Quotation $row) => fmt_date($row->issue_date)),
            Column::make('valid_until', 'quotations.valid_until')->sortable()->format(fn (Quotation $row) => fmt_date($row->valid_until))->hiddenByDefault(),
            Column::make('assigned_to', 'quotations.assigned_to')->format(fn (Quotation $row) => $row->assignee->name)->hiddenByDefault(),
            Column::make('status', 'quotations.status')->view('quotations.partials.status')->exportAs(fn (Quotation $row) => $row->displayStatus()->label())->onCard(),
            Column::make('total', 'quotations.total')->sortable()->summable()->alignEnd()
                ->format(fn (Quotation $row) => money($row->total))->exportAs(fn (Quotation $row) => $row->total)->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            SelectFilter::make('status', 'quotations.status')
                ->options(fn () => collect(QuotationStatus::cases())->mapWithKeys(fn (QuotationStatus $status) => [$status->value => $status->label()]))
                ->query(fn (Builder $query, string $value) => Quotation::whereDisplayStatus($query, $value)),
            SelectFilter::make('service_id', 'quotations.service')->options(fn () => Lookup::options(LookupType::Service)->pluck('name', 'id')),
            SelectFilter::make('assigned_to', 'quotations.assigned_to')->options(fn () => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            NumberRangeFilter::make('total', 'quotations.total'),
            BooleanFilter::make('expiring', 'quotations.expiring')->query(function (Builder $query, mixed $value) {
                if ((string) $value === '1') {
                    Quotation::constrainExpiring($query);
                }
            }),
        ];
    }

    protected function searchColumns(): array
    {
        return ['number', 'title_ar', 'title_en', 'client.name_ar', 'client.name_en'];
    }

    protected function dateColumn(): ?string
    {
        return 'issue_date';
    }

    protected function editUrl(Model $row): ?string
    {
        /** @var Quotation $row */
        return auth()->user()?->can('quotations.update') && ! $row->isLocked() ? route('quotations.edit', $row) : null;
    }
}
