<?php

namespace App\Livewire\Tables\Reports;

use App\Models\Client;
use App\Models\ReportRow;
use App\Services\Finance\ClientStatement;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Client statement of account (spec §9.2): opening balance, issued invoices and active receipts in
 * date order with the balance after each line. Empty until a client is chosen.
 */
class ClientStatementReport extends ReportTable
{
    public function tableKey(): string
    {
        return 'reports.statement';
    }

    protected function aggregate(): QueryBuilder
    {
        [$from, $to] = $this->period();

        return ClientStatement::query($this->clientId(), auth()->user(), $from, $to);
    }

    /** The chosen client when the user may see it. */
    private function clientId(): ?int
    {
        $id = (int) ($this->filters['client_id'] ?? 0);

        return $id > 0 && Client::query()->visibleTo(auth()->user())->whereKey($id)->exists() ? $id : null;
    }

    protected function columns(): array
    {
        $money = fn (string $key, string $label) => Column::make($key, $label)->alignEnd()
            ->format(fn (ReportRow $row) => bccomp((string) $row->getAttribute($key), '0', 2) === 0 ? '' : money((string) $row->getAttribute($key)))
            ->exportAs(fn (ReportRow $row) => $row->getAttribute($key));

        return [
            Column::make('entry_date', 'reports.date')->format(fn (ReportRow $row) => self::showDate((string) $row->getAttribute('entry_date')))->onCard(),
            Column::make('document', 'reports.statement_document')->view('reports.partials.statement-document')
                ->exportAs(fn (ReportRow $row) => trim(__('reports.entries.'.$row->getAttribute('entry')).' '.$row->getAttribute('number')))->onCard(),
            $money('debit', 'reports.debit')->summable(),
            $money('credit', 'reports.credit')->summable(),
            Column::make('balance', 'reports.balance')->alignEnd()
                ->format(fn (ReportRow $row) => money((string) $row->getAttribute('balance')))->exportAs(fn (ReportRow $row) => $row->getAttribute('balance'))->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return [
            // Read by aggregate(); the statement is always of one client.
            SelectFilter::make('client_id', 'finance.client')
                ->options(fn () => Client::query()->visibleTo(auth()->user())->orderBy('name_ar')->get()->mapWithKeys(fn (Client $client) => [$client->id => $client->name]))
                ->query(fn (Builder $query) => null),
        ];
    }

    protected function dateColumn(): ?string
    {
        return 'entry_date';
    }

    /** Lines keep date order so the running balance reads top to bottom. */
    protected function defaultSort(): array
    {
        return ['id', 'asc'];
    }

    private static function showDate(string $date): string
    {
        return implode('/', array_reverse(explode('-', substr($date, 0, 10))));
    }
}
