{{-- Subcontracts on the project purchases tab (spec §8.16). --}}
@can('subcontracts.view')
    <x-panel :title="__('resources.subcontracts.subcontracts')" class="panel-flush">
        @can('subcontracts.create')
            <x-slot:tools>
                <a href="{{ route('subcontracts.create', ['project' => $project->id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('resources.subcontracts.new') }}</a>
            </x-slot:tools>
        @endcan
        <livewire:tables.subcontracts-table :project-id="$project->id" />
    </x-panel>
@endcan
