{{-- Expenses of the project (spec §8.6, §8.12); the tab needs finance.costs. --}}
<x-panel :title="__('finance.expenses.expenses')" class="panel-flush">
    @can('expenses.create')
        <x-slot:tools>
            <a href="{{ route('expenses.create', ['project' => $project->id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('finance.expenses.new') }}</a>
        </x-slot:tools>
    @endcan
    <livewire:tables.expenses-table :project-id="$project->id" />
</x-panel>
