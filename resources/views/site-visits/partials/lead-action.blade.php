@can('site_visits.create')
    @unless ($lead->isClosed())
        <a href="{{ route('site-visits.create', ['lead' => $lead->id]) }}" class="btn btn-outline"><x-icon name="map-pin" :size="16" /> {{ __('visits.book_visit') }}</a>
    @endunless
@endcan
