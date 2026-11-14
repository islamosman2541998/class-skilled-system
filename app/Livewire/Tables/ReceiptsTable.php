<?php

namespace App\Livewire\Tables;

use App\Enums\LookupType;
use App\Enums\ReceiptStatus;
use App\Livewire\DataTable;
use App\Models\Client;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\User;
use App\Tables\Column;
use App\Tables\Filters\NumberRangeFilter;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class ReceiptsTable extends DataTable
{
    #[Locked]
    public ?int $projectId = null;

    #[Locked]
    public ?int $clientId = null;

    public function tableKey(): string
    {
        return match (true) {
            $this->projectId !== null => 'receipts.project',
            $this->clientId !== null => 'receipts.client',
            default => 'receipts',
        };
    }

    protected function permissionPrefix(): ?string
    {
        return 'receipts';
    }

    protected function query(): Builder
    {
        return Receipt::query()
            // Allocations are loaded, not summed in SQL: a select sub-query would break the totals query on MySQL.
            ->with(['client', 'project', 'paymentMethod', 'receiver', 'allocations:id,receipt_id,amount'])
            ->when($this->projectId, fn (Builder $query) => $query->where('project_id', $this->projectId))
            ->when($this->clientId, fn (Builder $query) => $query->where('client_id', $this->clientId));
    }

    protected function columns(): array
    {
        $unallocated = fn (Receipt $row) => bcsub((string) $row->amount, (string) $row->allocations->sum('amount'), 2);

        return [
            Column::make('number', 'finance.number')->sortable()->view('receipts.partials.number')->exportAs(fn (Receipt $row) => $row->number)->onCard(),
            Column::make('client', 'finance.client')->format(fn (Receipt $row) => $row->client->name)->onCard(),
            Column::make('project', 'finance.project')->format(fn (Receipt $row) => $row->project ? $row->project->number.' · '.$row->project->title : ''),
            Column::make('receipt_date', 'finance.receipts.date')->sortable()->format(fn (Receipt $row) => fmt_date($row->receipt_date))->onCard(),
            Column::make('payment_method_id', 'finance.receipts.method')->format(fn (Receipt $row) => $row->paymentMethod->name),
            Column::make('amount', 'finance.receipts.amount')->sortable()->summable()->alignEnd()->format(fn (Receipt $row) => money($row->amount))->exportAs(fn (Receipt $row) => $row->amount)->onCard(),
            Column::make('unallocated', 'finance.receipts.unallocated')->alignEnd()->format(fn (Receipt $row) => money($unallocated($row)))->exportAs($unallocated),
            Column::make('received_by', 'finance.receipts.received_by')->format(fn (Receipt $row) => $row->receiver->name)->hiddenByDefault(),
            Column::make('reference', 'finance.receipts.reference')->format(fn (Receipt $row) => (string) $row->reference)->hiddenByDefault(),
            Column::make('status', 'finance.status')->sortable()->view('receipts.partials.status')->exportAs(fn (Receipt $row) => $row->status->label()),
        ];
    }

    protected function filterDefinitions(): array
    {
        return array_values(array_filter([
            SelectFilter::make('status', 'finance.status')
                ->options(fn () => collect(ReceiptStatus::cases())->mapWithKeys(fn (ReceiptStatus $status) => [$status->value => $status->label()])),
            SelectFilter::make('payment_method_id', 'finance.receipts.method')->options(fn () => Lookup::options(LookupType::PaymentMethod)->pluck('name', 'id')),
            $this->clientId === null ? SelectFilter::make('client_id', 'finance.client')
                ->options(fn () => Client::query()->visibleTo(auth()->user())->whereHas('receipts')->orderBy('name_ar')->get()->mapWithKeys(fn (Client $client) => [$client->id => $client->name])) : null,
            $this->projectId === null ? SelectFilter::make('project_id', 'finance.project')
                ->options(fn () => Project::query()->visibleTo(auth()->user())->latest('id')->get()->mapWithKeys(fn (Project $project) => [$project->id => $project->number.' · '.$project->title])) : null,
            SelectFilter::make('received_by', 'finance.receipts.received_by')->options(fn () => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')),
            NumberRangeFilter::make('amount', 'finance.receipts.amount'),
        ]));
    }

    protected function searchColumns(): array
    {
        return ['number', 'reference', 'client.name_ar', 'client.name_en', 'project.number', 'project.title_ar'];
    }

    protected function dateColumn(): ?string
    {
        return 'receipt_date';
    }

    protected function defaultSort(): array
    {
        return ['receipt_date', 'desc'];
    }

    protected function editUrl(Model $row): ?string
    {
        return $row instanceof Receipt && $row->isActive() && auth()->user()?->can('receipts.update') ? route('receipts.edit', $row) : null;
    }
}
