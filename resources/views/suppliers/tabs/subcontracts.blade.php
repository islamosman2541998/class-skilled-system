<x-panel :title="__('resources.subcontracts.subcontracts')" class="panel-flush">
    @if ($supplier->type === \App\Enums\SupplierType::Subcontractor)
        @can('subcontracts.create')
            <x-slot:tools>
                <a href="{{ route('subcontracts.create', ['supplier' => $supplier->id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('resources.subcontracts.new') }}</a>
            </x-slot:tools>
        @endcan
    @endif
    <livewire:tables.subcontracts-table :supplier-id="$supplier->id" />
</x-panel>
