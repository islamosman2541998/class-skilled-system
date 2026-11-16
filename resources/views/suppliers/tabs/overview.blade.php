{{-- Supplier details (spec §8.15). --}}
<div class="row g-3">
    <div class="col-lg-6">
        <x-panel :title="__('resources.suppliers.identity')">
            <dl class="detail-list mb-0">
                <div><dt>{{ __('resources.suppliers.type') }}</dt><dd>{{ $supplier->type->label() }}</dd></div>
                @if ($supplier->specialty)<div><dt>{{ __('resources.suppliers.specialty') }}</dt><dd>{{ $supplier->specialty->name }}</dd></div>@endif
                @if ($supplier->phone)<div><dt>{{ __('app.phone') }}</dt><dd><bdi dir="ltr">{{ $supplier->phone }}</bdi></dd></div>@endif
                @if ($supplier->email)<div><dt>{{ __('app.email') }}</dt><dd><bdi dir="ltr">{{ $supplier->email }}</bdi></dd></div>@endif
                @if ($supplier->trn)<div><dt>{{ __('resources.suppliers.trn') }}</dt><dd><bdi dir="ltr">{{ $supplier->trn }}</bdi></dd></div>@endif
                @if ($supplier->address)<div><dt>{{ __('resources.suppliers.address') }}</dt><dd>{{ $supplier->address }}</dd></div>@endif
                <div><dt>{{ __('resources.suppliers.active') }}</dt><dd>{{ __('resources.suppliers.'.($supplier->is_active ? 'is_active' : 'is_inactive')) }}</dd></div>
            </dl>
        </x-panel>
    </div>
    <div class="col-lg-6">
        <x-panel :title="__('resources.suppliers.bank')">
            @if ($supplier->bank_name || $supplier->bank_iban || $supplier->bank_account_name)
                <dl class="detail-list mb-0">
                    @if ($supplier->bank_name)<div><dt>{{ __('resources.suppliers.bank_name') }}</dt><dd>{{ $supplier->bank_name }}</dd></div>@endif
                    @if ($supplier->bank_account_name)<div><dt>{{ __('resources.suppliers.bank_account_name') }}</dt><dd>{{ $supplier->bank_account_name }}</dd></div>@endif
                    @if ($supplier->bank_iban)<div><dt>{{ __('resources.suppliers.bank_iban') }}</dt><dd><bdi dir="ltr">{{ $supplier->bank_iban }}</bdi></dd></div>@endif
                </dl>
            @else
                <p class="text-muted mb-0">{{ __('resources.suppliers.no_bank') }}</p>
            @endif
        </x-panel>
        @if ($supplier->notes)
            <x-panel :title="__('resources.suppliers.notes')">
                <p class="text-pre mb-0">{{ $supplier->notes }}</p>
            </x-panel>
        @endif
    </div>
</div>
