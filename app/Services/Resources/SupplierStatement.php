<?php

namespace App\Services\Resources;

use App\Models\PaymentVoucher;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A supplier's statement of account (spec §9.2): purchase invoices are credits (owed to the
 * supplier), active payment vouchers debits, dated by invoice and voucher date. With a start date
 * the lines open with the balance before it; each line carries the running balance owed.
 */
class SupplierStatement
{
    /**
     * Lines ordered by "id": entry (opening, purchase, voucher), doc_id, number, entry_date, debit,
     * credit and balance. No supplier gives no lines.
     */
    public static function query(?int $supplierId, User $user, ?Carbon $from, ?Carbon $to): QueryBuilder
    {
        $purchases = self::purchases($supplierId, $user)
            ->selectRaw("'purchase' as entry, purchases.id as doc_id, purchases.number, purchases.purchase_date as entry_date, 1 as sort_key, 0 as debit, purchases.total as credit, purchases.total as delta");
        $vouchers = self::vouchers($supplierId, $user)
            ->selectRaw("'voucher' as entry, payment_vouchers.id as doc_id, payment_vouchers.number, payment_vouchers.voucher_date as entry_date, 2 as sort_key, payment_vouchers.amount as debit, 0 as credit, 0 - payment_vouchers.amount as delta");

        self::within($purchases, 'purchases.purchase_date', $from, $to);
        self::within($vouchers, 'payment_vouchers.voucher_date', $from, $to);
        $lines = $purchases->unionAll($vouchers);

        if ($supplierId !== null && $from !== null) {
            $lines->unionAll(DB::query()->selectRaw(
                "'opening' as entry, 0 as doc_id, NULL as number, ? as entry_date, 0 as sort_key, NULL as debit, NULL as credit, CAST(? AS DECIMAL(15, 2)) as delta",
                [$from->toDateString(), self::openingBalance($supplierId, $user, $from)],
            ));
        }

        $order = 'ORDER BY l.entry_date, l.sort_key, l.doc_id';

        return DB::query()->fromSub($lines, 'l')
            ->select(['l.entry', 'l.doc_id', 'l.number', 'l.entry_date', 'l.debit', 'l.credit'])
            ->selectRaw("ROW_NUMBER() OVER ({$order}) as id")
            ->selectRaw("CAST(SUM(l.delta) OVER ({$order} ROWS UNBOUNDED PRECEDING) AS DECIMAL(15, 2)) as balance");
    }

    /** Purchased minus paid before the start date. */
    public static function openingBalance(int $supplierId, User $user, Carbon $from): string
    {
        $purchased = (string) self::purchases($supplierId, $user)->whereDate('purchases.purchase_date', '<', $from->toDateString())->sum('purchases.total');
        $paid = (string) self::vouchers($supplierId, $user)->whereDate('payment_vouchers.voucher_date', '<', $from->toDateString())->sum('payment_vouchers.amount');

        return bcsub($purchased, $paid, 2);
    }

    private static function purchases(?int $supplierId, User $user): QueryBuilder
    {
        return Purchase::query()->visibleTo($user)->where('purchases.supplier_id', $supplierId ?? 0)->toBase();
    }

    private static function vouchers(?int $supplierId, User $user): QueryBuilder
    {
        return PaymentVoucher::query()->visibleTo($user)->active()->where('payment_vouchers.supplier_id', $supplierId ?? 0)->toBase();
    }

    private static function within(QueryBuilder $query, string $column, ?Carbon $from, ?Carbon $to): void
    {
        $query
            ->when($from, fn (QueryBuilder $q) => $q->whereDate($column, '>=', $from->toDateString()))
            ->when($to, fn (QueryBuilder $q) => $q->whereDate($column, '<=', $to->toDateString()));
    }
}
