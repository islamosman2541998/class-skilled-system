{{-- Invoices and receipts of the project (spec §8.6). Receipts are added under the invoices by Task 3. --}}
<x-panel :title="__('finance.invoices.invoices')" class="panel-flush">
    @can('invoices.create')
        <x-slot:tools>
            <a href="{{ route('invoices.create', ['project' => $project->id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('finance.invoices.new') }}</a>
        </x-slot:tools>
    @endcan
    <livewire:tables.invoices-table :project-id="$project->id" />
</x-panel>

@includeIf('projects.partials.receipts-list', ['project' => $project])
