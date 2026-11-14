{{-- Receipts of the project under its invoices (spec §8.6). --}}
@can('receipts.view')
    <x-panel :title="__('finance.receipts.receipts')" class="panel-flush mt-3">
        @can('receipts.create')
            <x-slot:tools>
                <a href="{{ route('receipts.create', ['client' => $project->client_id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('finance.receipts.new') }}</a>
            </x-slot:tools>
        @endcan
        <livewire:tables.receipts-table :project-id="$project->id" />
    </x-panel>
@endcan
