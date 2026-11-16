{{-- Purchases of the project (spec §8.6, §8.16); subcontracts join this tab in Task 3. --}}
<x-panel :title="__('resources.purchases.purchases')" class="panel-flush">
    @can('purchases.create')
        <x-slot:tools>
            <a href="{{ route('purchases.create', ['project' => $project->id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('resources.purchases.new') }}</a>
        </x-slot:tools>
    @endcan
    <livewire:tables.purchases-table :project-id="$project->id" />
</x-panel>

@foreach (\App\Http\Controllers\PurchaseController::$projectViews as $view)
    @include($view, ['project' => $project])
@endforeach
