{{-- Equipment on the project resources tab (spec §8.17). --}}
@can('equipment.view')
    <x-panel :title="__('resources.equipment.equipment')" class="panel-flush">
        <x-slot:tools>
            <span class="text-muted small">{{ __('resources.equipment.cost_total') }}: <bdi dir="ltr">{{ money((string) $project->equipmentAssignments()->sum('cost')) }}</bdi></span>
        </x-slot:tools>
        <livewire:tables.equipment-assignments-table :project-id="$project->id" />
    </x-panel>
@endcan
