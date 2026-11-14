@can('leads.create')
    <a href="{{ route('leads.create', ['client' => $client->id]) }}" class="btn btn-outline"><x-icon name="plus" :size="16" /> {{ __('leads.new_lead') }}</a>
@endcan
