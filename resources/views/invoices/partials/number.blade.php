<a href="{{ route('invoices.show', $row) }}" class="fw-semibold text-nowrap">
    @if ($row->number)
        <bdi dir="ltr">{{ $row->number }}</bdi>
    @else
        {{ __('finance.invoices.statuses.draft') }}
    @endif
</a>
