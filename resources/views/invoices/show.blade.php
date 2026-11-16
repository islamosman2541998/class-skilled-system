@extends('layouts.app')

@php
    $title = $invoice->number ?? __('finance.invoices.draft_title');
    $rate = rtrim(rtrim((string) $invoice->vat_rate, '0'), '.');
@endphp

@section('title', $title)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('invoices.index') }}">{{ __('finance.invoices.invoices') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$title" :status="$invoice->status->label()" :status-color="$invoice->status->color()">
        <x-slot:actions>
            @foreach (\App\Http\Controllers\InvoiceController::$actionViews as $view)
                @include($view, ['invoice' => $invoice])
            @endforeach
            @if ($invoice->isDraft())
                @can('invoices.update')
                    <form method="POST" action="{{ route('invoices.issue', $invoice) }}" data-confirm="{{ __('finance.invoices.issue_confirm') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-primary"><x-icon name="check" :size="16" /> {{ __('finance.invoices.issue') }}</button>
                    </form>
                    <a href="{{ route('invoices.edit', $invoice) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('finance.invoices.edit') }}</a>
                @endcan
                @can('invoices.delete')
                    <form method="POST" action="{{ route('invoices.destroy', $invoice) }}" data-confirm="{{ __('table.confirm_delete') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline text-danger"><x-icon name="trash" :size="16" /> {{ __('table.delete') }}</button>
                    </form>
                @endcan
            @elseif ($invoice->status->isActive())
                @can('invoices.cancel')
                    <button type="button" class="btn btn-outline text-danger" data-bs-toggle="modal" data-bs-target="#invoice-cancel">{{ __('finance.invoices.cancel') }}</button>
                @endcan
            @endif
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('finance.total') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($invoice->total) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('finance.invoices.paid') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($invoice->paid_amount) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('finance.invoices.remaining') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($invoice->remaining()) }}</bdi></span></div>
            <div class="figure">
                <span class="figure-label">{{ __('finance.invoices.due_date') }}</span>
                <span @class(['figure-value', 'text-danger' => $invoice->isOverdue()])><bdi dir="ltr">{{ fmt_date($invoice->due_date) }}</bdi></span>
            </div>
        </x-slot:figures>
    </x-record-header>

    <div class="row g-3">
        <div class="col-xl-8">
            <x-panel :title="__('finance.items')" class="panel-flush">
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
                            @foreach ($invoice->items as $item)
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
                            <tr><th colspan="4" class="text-end">{{ __('finance.subtotal') }}</th><td class="num"><bdi dir="ltr">{{ money($invoice->subtotal) }}</bdi></td></tr>
                            <tr><th colspan="4" class="text-end">{{ __('finance.vat') }} <bdi dir="ltr">({{ $rate }}%)</bdi></th><td class="num"><bdi dir="ltr">{{ money($invoice->vat_amount) }}</bdi></td></tr>
                            <tr class="q-grand"><th colspan="4" class="text-end">{{ __('finance.total') }}</th><td class="num"><bdi dir="ltr">{{ money($invoice->total) }}</bdi></td></tr>
                        </tfoot>
                    </table>
                </div>
            </x-panel>

            @foreach (\App\Http\Controllers\InvoiceController::$showViews as $view)
                @include($view, ['invoice' => $invoice])
            @endforeach

            @if ($invoice->terms_ar || $invoice->terms_en || $invoice->notes)
                <x-panel :title="__('finance.invoices.terms')">
                    @if (app()->getLocale() === 'en' && $invoice->terms_en)
                        <p class="text-pre mb-0" dir="ltr">{{ $invoice->terms_en }}</p>
                    @elseif ($invoice->terms_ar)
                        <p class="text-pre mb-0" dir="rtl">{{ $invoice->terms_ar }}</p>
                    @endif
                    @if ($invoice->notes)
                        <h3 class="sub-title mt-3">{{ __('finance.notes') }}</h3>
                        <p class="text-pre mb-0">{{ $invoice->notes }}</p>
                    @endif
                </x-panel>
            @endif
        </div>
        <div class="col-xl-4">
            <x-panel :title="__('finance.invoices.invoice')">
                <dl class="detail-list">
                    <div><dt>{{ __('finance.client') }}</dt><dd><a href="{{ route('clients.show', $invoice->client) }}">{{ $invoice->client->name }}</a></dd></div>
                    <div><dt>{{ __('finance.project') }}</dt><dd><a href="{{ route('projects.show', [$invoice->project, 'tab' => 'invoices']) }}"><bdi dir="ltr">{{ $invoice->project->number }}</bdi></a> · {{ $invoice->project->title }}</dd></div>
                    <div><dt>{{ __('finance.invoices.issue_date') }}</dt><dd><bdi dir="ltr">{{ fmt_date($invoice->issue_date) }}</bdi></dd></div>
                    @if ($invoice->retention_release)
                        <div><dt>{{ __('site.retention.invoice') }}</dt><dd>{{ __('site.retention.label') }}</dd></div>
                    @endif
                    @if ($invoice->claim)
                        <div><dt>{{ __('site.claims.claim') }}</dt><dd><a href="{{ route('claims.show', $invoice->claim) }}"><bdi dir="ltr">{{ $invoice->claim->number }}</bdi></a></dd></div>
                    @endif
                    @if ($invoice->installment)
                        <div><dt>{{ __('finance.invoices.installment') }}</dt><dd>{{ $invoice->installment->label() }}</dd></div>
                    @endif
                    @if ($invoice->replaces)
                        <div><dt>{{ __('finance.invoices.replaces') }}</dt><dd><a href="{{ route('invoices.show', $invoice->replaces) }}"><bdi dir="ltr">{{ $invoice->replaces->number }}</bdi></a></dd></div>
                    @endif
                    @if ($invoice->issued_at)
                        <div><dt>{{ __('finance.invoices.issued_by') }}</dt><dd>{{ $invoice->issuer?->name }} · <bdi dir="ltr">{{ fmt_datetime($invoice->issued_at) }}</bdi></dd></div>
                    @endif
                    @if ($invoice->cancelled_at)
                        <div><dt>{{ __('finance.invoices.cancelled_by') }}</dt><dd>{{ $invoice->canceller?->name }} · <bdi dir="ltr">{{ fmt_datetime($invoice->cancelled_at) }}</bdi></dd></div>
                        <div><dt>{{ __('finance.reason') }}</dt><dd class="text-pre">{{ $invoice->cancel_reason }}</dd></div>
                    @endif
                </dl>
            </x-panel>
            <x-activity-tab :subject="$invoice" />
        </div>
    </div>

    @if ($invoice->status->isActive())
        @can('invoices.cancel')
            @push('modals')
                <div class="modal fade" id="invoice-cancel" tabindex="-1" aria-labelledby="invoice-cancel-title" aria-hidden="true">
                    <div class="modal-dialog">
                        <form class="modal-content" method="POST" action="{{ route('invoices.cancel', $invoice) }}">
                            @csrf
                            @method('PATCH')
                            <div class="modal-header">
                                <h2 class="modal-title" id="invoice-cancel-title">{{ __('finance.invoices.cancel') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                            </div>
                            <div class="modal-body">
                                <x-field name="reason" :label="__('finance.reason')" for="invoice-cancel-reason" required>
                                    <textarea id="invoice-cancel-reason" name="reason" class="form-control" rows="2" maxlength="2000" required></textarea>
                                </x-field>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="replace" value="1" id="invoice-cancel-replace">
                                    <label class="form-check-label" for="invoice-cancel-replace">{{ __('finance.invoices.cancel_replace') }}</label>
                                </div>
                                <p class="form-text mb-0">{{ __('finance.invoices.replace_hint') }}</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                                <button type="submit" class="btn btn-danger">{{ __('finance.invoices.cancel') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endpush
        @endcan
    @endif
@endsection
