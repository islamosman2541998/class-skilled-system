<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A client's statement of account (spec §9.2): issued invoices are debits, active receipts credits,
 * dated by issue date and receipt date. With a start date the lines open with the balance before it.
 * Each line carries the running balance. The report table and the PDF share this query.
 */
class ClientStatement
{
    /**
     * Lines ordered by "id": entry (opening, invoice, receipt), doc_id, number, entry_date, debit,
     * credit and balance. No client gives no lines.
     */
    public static function query(?int $clientId, User $user, ?Carbon $from, ?Carbon $to): QueryBuilder
    {
        $invoices = self::invoices($clientId, $user)
            ->selectRaw("'invoice' as entry, invoices.id as doc_id, invoices.number, invoices.issue_date as entry_date, 1 as sort_key, invoices.total as debit, 0 as credit, invoices.total as delta");
        $receipts = self::receipts($clientId, $user)
            ->selectRaw("'receipt' as entry, receipts.id as doc_id, receipts.number, receipts.receipt_date as entry_date, 2 as sort_key, 0 as debit, receipts.amount as credit, 0 - receipts.amount as delta");

        self::within($invoices, 'invoices.issue_date', $from, $to);
        self::within($receipts, 'receipts.receipt_date', $from, $to);
        $lines = $invoices->unionAll($receipts);

        if ($clientId !== null && $from !== null) {
            $lines->unionAll(DB::query()->selectRaw(
                "'opening' as entry, 0 as doc_id, NULL as number, ? as entry_date, 0 as sort_key, NULL as debit, NULL as credit, CAST(? AS DECIMAL(15, 2)) as delta",
                [$from->toDateString(), self::openingBalance($clientId, $user, $from)],
            ));
        }

        $order = 'ORDER BY l.entry_date, l.sort_key, l.doc_id';

        return DB::query()->fromSub($lines, 'l')
            ->select(['l.entry', 'l.doc_id', 'l.number', 'l.entry_date', 'l.debit', 'l.credit'])
            ->selectRaw("ROW_NUMBER() OVER ({$order}) as id")
            ->selectRaw("CAST(SUM(l.delta) OVER ({$order} ROWS UNBOUNDED PRECEDING) AS DECIMAL(15, 2)) as balance");
    }

    /** Invoiced minus received before the start date. */
    public static function openingBalance(int $clientId, User $user, Carbon $from): string
    {
        $invoiced = (string) self::invoices($clientId, $user)->whereDate('invoices.issue_date', '<', $from->toDateString())->sum('invoices.total');
        $received = (string) self::receipts($clientId, $user)->whereDate('receipts.receipt_date', '<', $from->toDateString())->sum('receipts.amount');

        return bcsub($invoiced, $received, 2);
    }

    private static function invoices(?int $clientId, User $user): QueryBuilder
    {
        return Invoice::query()->visibleTo($user)->active()->where('invoices.client_id', $clientId ?? 0)->toBase();
    }

    private static function receipts(?int $clientId, User $user): QueryBuilder
    {
        return Receipt::query()->visibleTo($user)->active()->where('receipts.client_id', $clientId ?? 0)->toBase();
    }

    private static function within(QueryBuilder $query, string $column, ?Carbon $from, ?Carbon $to): void
    {
        $query
            ->when($from, fn (QueryBuilder $q) => $q->whereDate($column, '>=', $from->toDateString()))
            ->when($to, fn (QueryBuilder $q) => $q->whereDate($column, '<=', $to->toDateString()));
    }
}
