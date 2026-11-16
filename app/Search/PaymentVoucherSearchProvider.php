<?php

namespace App\Search;

use App\Models\PaymentVoucher;
use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;

class PaymentVoucherSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'payment_vouchers';
    }

    public function labelKey(): string
    {
        return 'resources.vouchers.vouchers';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('payment_vouchers.view')) {
            return collect();
        }

        return Like::any(PaymentVoucher::query()->with('supplier')->visibleTo($user), ['number', 'reference'], $term)
            ->latest('voucher_date')
            ->limit($limit)
            ->get()
            ->map(fn (PaymentVoucher $voucher) => new SearchResult($voucher->number, $voucher->supplier->name.' · '.money($voucher->amount), route('payment-vouchers.show', $voucher)));
    }
}
