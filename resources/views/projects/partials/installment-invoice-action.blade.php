{{-- The invoice of an installment, or "create invoice" when it is due (spec §8.8). --}}
@php
    $rowInvoice = auth()->user()->can('invoices.view')
        ? $row->invoices()->where('status', '!=', \App\Enums\InvoiceStatus::Cancelled->value)->latest('id')->first()
        : null;
@endphp
@if ($rowInvoice)
    <a href="{{ route('invoices.show', $rowInvoice) }}" class="ms-1 text-nowrap">
        @if ($rowInvoice->number)
            <bdi dir="ltr">{{ $rowInvoice->number }}</bdi>
        @else
            {{ __('finance.invoices.statuses.draft') }}
        @endif
    </a>
@elseif ($row->status === \App\Enums\InstallmentStatus::Due)
    @can('invoices.create')
        <form method="POST" action="{{ route('projects.installments.invoice', [$project, $row]) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline btn-sm ms-1"><x-icon name="file-invoice" :size="14" /> {{ __('finance.invoices.create_from_installment') }}</button>
        </form>
    @endcan
@endif
