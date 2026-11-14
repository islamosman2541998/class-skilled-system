@can('projects.create')
    <a href="{{ route('projects.create', ['client' => $client->id]) }}" class="btn btn-outline"><x-icon name="building-skyscraper" :size="16" /> {{ __('projects.new_project') }}</a>
@endcan
