@extends('layouts.app')

@section('title', $voucher->number)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('payment-vouchers.index') }}">{{ __('resources.vouchers.vouchers') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$voucher->number" :status="$voucher->status->label()" :status-color="$voucher->status->color()">
        <x-slot:actions>
            @if ($voucher->isActive())
                @can('payment_vouchers.update')
                    <a href="{{ route('payment-vouchers.edit', $voucher) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('resources.vouchers.edit') }}</a>
                @endcan
                @can('payment_vouchers.cancel')
                    <button type="button" class="btn btn-outline text-danger" data-bs-toggle="modal" data-bs-target="#voucher-cancel">{{ __('resources.vouchers.cancel') }}</button>
                @endcan
            @endif
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('resources.vouchers.amount') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($voucher->amount) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('resources.vouchers.allocated') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($voucher->allocations->sum('amount')) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('resources.vouchers.date') }}</span><span class="figure-value"><bdi dir="ltr">{{ fmt_date($voucher->voucher_date) }}</bdi></span></div>
        </x-slot:figures>
    </x-record-header>

    <div class="row g-3">
        <div class="col-xl-8">
            <x-panel :title="__('resources.vouchers.allocations')" class="panel-flush">
                @if ($voucher->allocations->isEmpty())
                    <x-empty-state :message="__('resources.vouchers.no_allocations')" icon="cash-banknote" />
                @else
                    <div class="table-responsive">
                        <table class="table q-view mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('resources.purchases.purchase') }}</th>
                                    <th scope="col">{{ __('resources.purchases.project') }}</th>
                                    <th scope="col" class="num">{{ __('resources.vouchers.amount') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($voucher->allocations as $allocation)
                                    <tr>
                                        <td><a href="{{ route('purchases.show', $allocation->purchase) }}"><bdi dir="ltr">{{ $allocation->purchase->number }}</bdi></a> · <bdi dir="ltr">{{ $allocation->purchase->supplier_invoice_no }}</bdi></td>
                                        <td><bdi dir="ltr">{{ $allocation->purchase->project->number }}</bdi></td>
                                        <td class="num"><bdi dir="ltr">{{ money($allocation->amount) }}</bdi></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-panel>

            @if ($voucher->isActive())
                @can('payment_vouchers.update')
                    <x-panel :title="__('resources.vouchers.change_allocations')">
                        <form method="POST" action="{{ route('payment-vouchers.allocations.update', $voucher) }}">
                            @csrf
                            @method('PUT')
                            @include('payment-vouchers.partials.allocations', ['allocations' => old('allocations', $voucher->allocations->pluck('amount', 'purchase_id')->all())])
                            @if ($openPurchases->isNotEmpty())
                                <div class="d-flex justify-content-end mt-3">
                                    <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                                </div>
                            @endif
                        </form>
                    </x-panel>
                @endcan
            @endif

            <x-attachments :model="$voucher" :action="route('payment-vouchers.attachments.store', $voucher)" />
        </div>
        <div class="col-xl-4">
            <x-panel :title="__('resources.vouchers.voucher')">
                <dl class="detail-list">
                    <div><dt>{{ __('resources.vouchers.supplier') }}</dt><dd><a href="{{ route('suppliers.show', $voucher->supplier) }}">{{ $voucher->supplier->name }}</a></dd></div>
                    <div><dt>{{ __('resources.vouchers.method') }}</dt><dd>{{ $voucher->paymentMethod?->name }}</dd></div>
                    @if ($voucher->reference)<div><dt>{{ __('resources.vouchers.reference') }}</dt><dd><bdi dir="ltr">{{ $voucher->reference }}</bdi></dd></div>@endif
                    @if ($voucher->creator)<div><dt>{{ __('resources.purchases.created_by') }}</dt><dd>{{ $voucher->creator->name }}</dd></div>@endif
                    @if ($voucher->notes)<div><dt>{{ __('resources.vouchers.notes') }}</dt><dd class="text-pre">{{ $voucher->notes }}</dd></div>@endif
                    @if ($voucher->cancelled_at)
                        <div><dt>{{ __('resources.vouchers.cancelled_by') }}</dt><dd>{{ $voucher->canceller?->name }} · <bdi dir="ltr">{{ fmt_datetime($voucher->cancelled_at) }}</bdi></dd></div>
                        <div><dt>{{ __('finance.reason') }}</dt><dd class="text-pre">{{ $voucher->cancel_reason }}</dd></div>
                    @endif
                </dl>
            </x-panel>
            <x-activity-tab :subject="$voucher" />
        </div>
    </div>

    @if ($voucher->isActive())
        @can('payment_vouchers.cancel')
            @push('modals')
                <div class="modal fade" id="voucher-cancel" tabindex="-1" aria-labelledby="voucher-cancel-title" aria-hidden="true">
                    <div class="modal-dialog">
                        <form class="modal-content" method="POST" action="{{ route('payment-vouchers.cancel', $voucher) }}">
                            @csrf
                            @method('PATCH')
                            <div class="modal-header">
                                <h2 class="modal-title" id="voucher-cancel-title">{{ __('resources.vouchers.cancel') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                            </div>
                            <div class="modal-body">
                                <x-field name="reason" :label="__('finance.reason')" for="voucher-cancel-reason" required>
                                    <textarea id="voucher-cancel-reason" name="reason" class="form-control" rows="2" maxlength="2000" required></textarea>
                                </x-field>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                                <button type="submit" class="btn btn-danger">{{ __('resources.vouchers.cancel') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endpush
        @endcan
    @endif
@endsection
