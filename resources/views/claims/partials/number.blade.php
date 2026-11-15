<a href="{{ route('claims.show', $row) }}" class="fw-semibold text-nowrap">
    @if ($row->number)
        <bdi dir="ltr">{{ $row->number }}</bdi>
    @else
        {{ __('site.claims.statuses.draft') }}
    @endif
</a>
