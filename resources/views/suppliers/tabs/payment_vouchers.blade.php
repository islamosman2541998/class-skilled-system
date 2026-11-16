<x-panel :title="__('resources.vouchers.vouchers')" class="panel-flush">
    @can('payment_vouchers.create')
        <x-slot:tools>
            <a href="{{ route('payment-vouchers.create', ['supplier' => $supplier->id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('resources.vouchers.new') }}</a>
        </x-slot:tools>
    @endcan
    <livewire:tables.payment-vouchers-table :supplier-id="$supplier->id" />
</x-panel>
