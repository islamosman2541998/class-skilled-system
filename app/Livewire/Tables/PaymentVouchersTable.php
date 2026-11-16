<?php

namespace App\Livewire\Tables;

use App\Enums\LookupType;
use App\Enums\PaymentVoucherStatus;
use App\Livewire\DataTable;
use App\Models\Lookup;
use App\Models\PaymentVoucher;
use App\Models\Supplier;
use App\Tables\Column;
use App\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;

/**
 * Payment vouchers (spec §8.16): filters supplier, method, status and the voucher date.
 */
class PaymentVouchersTable extends DataTable
{
    #[Locked]
    public ?int $supplierId = null;

    public function tableKey(): string
    {
        return $this->supplierId !== null ? 'payment_vouchers.supplier' : 'payment_vouchers';
    }

    protected function permissionPrefix(): ?string
    {
        return 'payment_vouchers';
    }

    protected function query(): Builder
    {
        return PaymentVoucher::query()
            ->with(['supplier', 'paymentMethod'])
            ->withSum('allocations as allocated_total', 'amount')
            ->when($this->supplierId, fn (Builder $query) => $query->where('supplier_id', $this->supplierId));
    }

    protected function columns(): array
    {
        return [
            Column::make('number', 'resources.vouchers.number')->sortable()->view('payment-vouchers.partials.number')->exportAs(fn (PaymentVoucher $row) => $row->number)->onCard(),
            Column::make('supplier', 'resources.vouchers.supplier')->format(fn (PaymentVoucher $row) => $row->supplier->name)->onCard(),
            Column::make('voucher_date', 'resources.vouchers.date')->sortable()->format(fn (PaymentVoucher $row) => fmt_date($row->voucher_date))->onCard(),
            Column::make('amount', 'resources.vouchers.amount')->sortable()->summable()->alignEnd()->format(fn (PaymentVoucher $row) => money($row->amount))->exportAs(fn (PaymentVoucher $row) => $row->amount)->onCard(),
            Column::make('allocated_total', 'resources.vouchers.allocated')->alignEnd()
                ->format(fn (PaymentVoucher $row) => money((string) ($row->getAttribute('allocated_total') ?? '0')))->exportAs(fn (PaymentVoucher $row) => $row->getAttribute('allocated_total') ?? '0'),
            Column::make('payment_method', 'resources.vouchers.method')->format(fn (PaymentVoucher $row) => (string) $row->paymentMethod->name),
            Column::make('status', 'resources.vouchers.status')->sortable()->view('payment-vouchers.partials.status')->exportAs(fn (PaymentVoucher $row) => $row->status->label())->onCard(),
        ];
    }

    protected function filterDefinitions(): array
    {
        return array_values(array_filter([
            $this->supplierId === null ? SelectFilter::make('supplier_id', 'resources.vouchers.supplier')
                ->options(fn () => Supplier::query()->orderBy('name_ar')->get()->mapWithKeys(fn (Supplier $supplier) => [$supplier->id => $supplier->name])) : null,
            SelectFilter::make('payment_method_id', 'resources.vouchers.method')->options(fn () => Lookup::options(LookupType::PaymentMethod)->pluck('name', 'id')),
            SelectFilter::make('status', 'resources.vouchers.status')
                ->options(fn () => collect(PaymentVoucherStatus::cases())->mapWithKeys(fn (PaymentVoucherStatus $status) => [$status->value => $status->label()])),
        ]));
    }

    protected function searchColumns(): array
    {
        return ['number', 'reference', 'supplier.name_ar', 'supplier.name_en'];
    }

    protected function dateColumn(): ?string
    {
        return 'voucher_date';
    }

    protected function defaultSort(): array
    {
        return ['voucher_date', 'desc'];
    }
}
