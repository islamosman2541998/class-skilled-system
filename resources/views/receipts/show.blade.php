@extends('layouts.app')

@section('title', $receipt->number)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('receipts.index') }}">{{ __('finance.receipts.receipts') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$receipt->number" :status="$receipt->status->label()" :status-color="$receipt->status->color()">
        <x-slot:actions>
            @include('receipts.partials.share-actions')
            @if ($receipt->isActive())
                @can('receipts.update')
                    <a href="{{ route('receipts.edit', $receipt) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('finance.receipts.edit') }}</a>
                @endcan
                @can('receipts.cancel')
                    <button type="button" class="btn btn-outline text-danger" data-bs-toggle="modal" data-bs-target="#receipt-cancel">{{ __('finance.receipts.cancel') }}</button>
                @endcan
            @endif
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('finance.receipts.amount') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($receipt->amount) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('finance.receipts.allocated') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($receipt->allocated()) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('finance.receipts.unallocated') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($receipt->unallocated()) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('finance.receipts.date') }}</span><span class="figure-value"><bdi dir="ltr">{{ fmt_date($receipt->receipt_date) }}</bdi></span></div>
        </x-slot:figures>
    </x-record-header>

    <div class="row g-3">
        <div class="col-xl-8">
            <x-panel :title="__('finance.receipts.allocations')">
                @if ($receipt->isActive() && auth()->user()->can('receipts.update'))
                    <form method="POST" action="{{ route('receipts.allocations.update', $receipt) }}" novalidate>
                        @csrf
                        @method('PUT')
                        <p class="form-text mt-0">{{ __('finance.receipts.allocations_hint') }}</p>
                        @include('receipts.partials.allocations', ['allocations' => old('allocations', $receipt->allocations->pluck('amount', 'invoice_id')->all())])
                        @if ($openInvoices->isNotEmpty())
                            <button type="submit" class="btn btn-primary btn-sm mt-3">{{ __('finance.receipts.save_allocations') }}</button>
                        @endif
                    </form>
                @elseif ($receipt->allocations->isEmpty())
                    <p class="text-muted mb-0">{{ __('finance.receipts.no_open_invoices') }}</p>
                @else
                    <dl class="detail-list">
                        @foreach ($receipt->allocations as $allocation)
                            <div><dt><a href="{{ route('invoices.show', $allocation->invoice) }}"><bdi dir="ltr">{{ $allocation->invoice->number }}</bdi></a></dt><dd><bdi dir="ltr">{{ money($allocation->amount) }}</bdi></dd></div>
                        @endforeach
                    </dl>
                @endif
            </x-panel>
            <x-activity-tab :subject="$receipt" />
        </div>
        <div class="col-xl-4">
            <x-panel :title="__('finance.receipts.receipt')">
                <dl class="detail-list">
                    <div><dt>{{ __('finance.client') }}</dt><dd><a href="{{ route('clients.show', $receipt->client) }}">{{ $receipt->client->name }}</a></dd></div>
                    @if ($receipt->project)
                        <div><dt>{{ __('finance.project') }}</dt><dd><a href="{{ route('projects.show', [$receipt->project, 'tab' => 'invoices']) }}"><bdi dir="ltr">{{ $receipt->project->number }}</bdi></a> · {{ $receipt->project->title }}</dd></div>
                    @endif
                    <div><dt>{{ __('finance.receipts.method') }}</dt><dd>{{ $receipt->paymentMethod->name }}</dd></div>
                    @if ($receipt->reference)
                        <div><dt>{{ __('finance.receipts.reference') }}</dt><dd><bdi dir="ltr">{{ $receipt->reference }}</bdi></dd></div>
                    @endif
                    @if ($receipt->cheque_date)
                        <div><dt>{{ __('finance.receipts.cheque_date') }}</dt><dd><bdi dir="ltr">{{ fmt_date($receipt->cheque_date) }}</bdi></dd></div>
                    @endif
                    <div><dt>{{ __('finance.receipts.received_by') }}</dt><dd>{{ $receipt->receiver->name }}</dd></div>
                    @if ($receipt->cancelled_at)
                        <div><dt>{{ __('finance.receipts.cancelled_by') }}</dt><dd>{{ $receipt->canceller?->name }} · <bdi dir="ltr">{{ fmt_datetime($receipt->cancelled_at) }}</bdi></dd></div>
                        <div><dt>{{ __('finance.reason') }}</dt><dd class="text-pre">{{ $receipt->cancel_reason }}</dd></div>
                    @endif
                </dl>
                @if ($receipt->notes)
                    <p class="text-pre mt-3 mb-0">{{ $receipt->notes }}</p>
                @endif
            </x-panel>
            <x-attachments :model="$receipt" :action="route('receipts.attachments.store', $receipt)" />
        </div>
    </div>

    @if ($receipt->isActive())
        @can('receipts.cancel')
            @push('modals')
                <div class="modal fade" id="receipt-cancel" tabindex="-1" aria-labelledby="receipt-cancel-title" aria-hidden="true">
                    <div class="modal-dialog">
                        <form class="modal-content" method="POST" action="{{ route('receipts.cancel', $receipt) }}">
                            @csrf
                            @method('PATCH')
                            <div class="modal-header">
                                <h2 class="modal-title" id="receipt-cancel-title">{{ __('finance.receipts.cancel') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                            </div>
                            <div class="modal-body">
                                <x-field name="reason" :label="__('finance.reason')" for="receipt-cancel-reason" required>
                                    <textarea id="receipt-cancel-reason" name="reason" class="form-control" rows="2" maxlength="2000" required></textarea>
                                </x-field>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                                <button type="submit" class="btn btn-danger">{{ __('finance.receipts.cancel') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endpush
        @endcan
    @endif
@endsection
