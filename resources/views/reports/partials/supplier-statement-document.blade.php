@php($entry = $row->getAttribute('entry'))
@if ($entry === 'purchase')
    {{ __('resources.statement.entries.purchase') }} <a href="{{ route('purchases.show', $row->getAttribute('doc_id')) }}" class="text-nowrap"><bdi dir="ltr">{{ $row->getAttribute('number') }}</bdi></a>
@elseif ($entry === 'voucher')
    {{ __('resources.statement.entries.voucher') }} <a href="{{ route('payment-vouchers.show', $row->getAttribute('doc_id')) }}" class="text-nowrap"><bdi dir="ltr">{{ $row->getAttribute('number') }}</bdi></a>
@else
    <span class="fw-semibold">{{ __('resources.statement.entries.opening') }}</span>
@endif
