@php($entry = $row->getAttribute('entry'))
@if ($entry === 'invoice')
    {{ __('reports.entries.invoice') }} <a href="{{ route('invoices.show', $row->getAttribute('doc_id')) }}" class="text-nowrap"><bdi dir="ltr">{{ $row->getAttribute('number') }}</bdi></a>
@elseif ($entry === 'receipt')
    {{ __('reports.entries.receipt') }} <a href="{{ route('receipts.show', $row->getAttribute('doc_id')) }}" class="text-nowrap"><bdi dir="ltr">{{ $row->getAttribute('number') }}</bdi></a>
@else
    <span class="fw-semibold">{{ __('reports.entries.opening') }}</span>
@endif
