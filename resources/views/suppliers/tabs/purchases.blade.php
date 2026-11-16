<x-panel :title="__('resources.purchases.purchases')" class="panel-flush">
    @can('purchases.create')
        <x-slot:tools>
            <a href="{{ route('purchases.create', ['supplier' => $supplier->id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('resources.purchases.new') }}</a>
        </x-slot:tools>
    @endcan
    <livewire:tables.purchases-table :supplier-id="$supplier->id" />
</x-panel>
