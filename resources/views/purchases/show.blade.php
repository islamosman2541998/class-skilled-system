@extends('layouts.app')

@php
    $rate = rtrim(rtrim((string) $purchase->vat_rate, '0'), '.') ?: '0';
@endphp

@section('title', $purchase->number)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('purchases.index') }}">{{ __('resources.purchases.purchases') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$purchase->number" :status="$purchase->status->label()" :status-color="$purchase->status->color()">
        <x-slot:actions>
            @can('purchases.update')
                <a href="{{ route('purchases.edit', $purchase) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('resources.purchases.edit') }}</a>
            @endcan
            @can('purchases.delete')
                @if (bccomp((string) $purchase->paid_amount, '0', 2) === 0)
                    <form method="POST" action="{{ route('purchases.destroy', $purchase) }}" data-confirm="{{ __('table.confirm_delete') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline text-danger"><x-icon name="trash" :size="16" /> {{ __('table.delete') }}</button>
                    </form>
                @endif
            @endcan
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('resources.purchases.total') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($purchase->total) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('resources.purchases.paid') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($purchase->paid_amount) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('resources.purchases.remaining') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($purchase->remaining()) }}</bdi></span></div>
        </x-slot:figures>
    </x-record-header>

    <div class="row g-3">
        <div class="col-xl-8">
            <x-panel :title="__('resources.purchases.items')" class="panel-flush">
                <div class="table-responsive">
                    <table class="table q-view mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('finance.description_ar') }}</th>
                                <th scope="col">{{ __('finance.unit') }}</th>
                                <th scope="col" class="num">{{ __('finance.quantity') }}</th>
                                <th scope="col" class="num">{{ __('finance.unit_price') }}</th>
                                <th scope="col" class="num">{{ __('finance.line_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($purchase->items as $item)
                                <tr>
                                    <td>{{ $item->description() }}</td>
                                    <td>{{ $item->unit?->name }}</td>
                                    <td class="num"><bdi dir="ltr">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</bdi></td>
                                    <td class="num"><bdi dir="ltr">{{ money($item->unit_price, false) }}</bdi></td>
                                    <td class="num"><bdi dir="ltr">{{ money($item->line_total, false) }}</bdi></td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th colspan="4" class="text-end">{{ __('resources.purchases.subtotal') }}</th><td class="num"><bdi dir="ltr">{{ money($purchase->subtotal) }}</bdi></td></tr>
                            <tr><th colspan="4" class="text-end">{{ __('resources.purchases.vat') }} <bdi dir="ltr">({{ $rate }}%)</bdi></th><td class="num"><bdi dir="ltr">{{ money($purchase->vat_amount) }}</bdi></td></tr>
                            <tr class="q-grand"><th colspan="4" class="text-end">{{ __('resources.purchases.total') }}</th><td class="num"><bdi dir="ltr">{{ money($purchase->total) }}</bdi></td></tr>
                        </tfoot>
                    </table>
                </div>
            </x-panel>

            @foreach (\App\Http\Controllers\PurchaseController::$showViews as $view)
                @include($view, ['purchase' => $purchase])
            @endforeach

            <x-attachments :model="$purchase" :action="route('purchases.attachments.store', $purchase)" />
        </div>
        <div class="col-xl-4">
            <x-panel :title="__('resources.purchases.purchase')">
                <dl class="detail-list">
                    <div><dt>{{ __('resources.purchases.supplier') }}</dt><dd><a href="{{ route('suppliers.show', $purchase->supplier) }}">{{ $purchase->supplier->name }}</a></dd></div>
                    <div><dt>{{ __('resources.purchases.project') }}</dt><dd><a href="{{ route('projects.show', [$purchase->project, 'tab' => 'purchases']) }}"><bdi dir="ltr">{{ $purchase->project->number }}</bdi></a> · {{ $purchase->project->title }}</dd></div>
                    <div><dt>{{ __('resources.purchases.supplier_invoice_no') }}</dt><dd><bdi dir="ltr">{{ $purchase->supplier_invoice_no }}</bdi></dd></div>
                    <div><dt>{{ __('resources.purchases.date') }}</dt><dd><bdi dir="ltr">{{ fmt_date($purchase->purchase_date) }}</bdi></dd></div>
                    @if ($purchase->creator)<div><dt>{{ __('resources.purchases.created_by') }}</dt><dd>{{ $purchase->creator->name }}</dd></div>@endif
                    @if ($purchase->notes)<div><dt>{{ __('resources.purchases.notes') }}</dt><dd class="text-pre">{{ $purchase->notes }}</dd></div>@endif
                </dl>
            </x-panel>
            <x-activity-tab :subject="$purchase" />
        </div>
    </div>
@endsection
