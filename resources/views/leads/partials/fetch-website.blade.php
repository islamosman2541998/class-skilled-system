@can('leads.create')
    @if (app(\App\Settings\IntegrationSettings::class)->website_url)
        <form method="POST" action="{{ route('leads.fetch-website') }}">
            @csrf
            <button type="submit" class="btn btn-outline"><x-icon name="refresh" :size="16" /> {{ __('leads.website.fetch') }}</button>
        </form>
    @endif
@endcan
