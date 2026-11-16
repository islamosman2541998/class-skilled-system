@extends('layouts.app')

@php
    $editing = $voucher->exists;
    $title = $editing ? __('resources.vouchers.edit') : __('resources.vouchers.new');
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('resources.vouchers.vouchers') => route('payment-vouchers.index')] + ($editing ? [$voucher->number => route('payment-vouchers.show', $voucher)] : [])" />

    <form method="POST" action="{{ $editing ? route('payment-vouchers.update', $voucher) : route('payment-vouchers.store') }}" data-dirty-check novalidate data-voucher-form>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('resources.vouchers.voucher')">
            <div class="form-grid">
                @if ($editing)
                    <div><span class="form-label d-block">{{ __('resources.vouchers.supplier') }}</span>{{ $voucher->supplier->name }}</div>
                @else
                    <x-field name="supplier_id" :label="__('resources.vouchers.supplier')" required>
                        {{-- Choosing a supplier reloads the page with that supplier's open purchase invoices. --}}
                        <select id="supplier_id" name="supplier_id" class="form-select" data-select data-reload-param="supplier" required>
                            <option value=""></option>
                            @foreach ($suppliers as $option)
                                <option value="{{ $option->id }}" @selected($supplier?->id === $option->id)>{{ $option->name }} · {{ $option->type->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field name="voucher_date" :label="__('resources.vouchers.date')" required>
                    <input id="voucher_date" name="voucher_date" value="{{ old('voucher_date', $voucher->voucher_date?->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('voucher_date')]) data-date dir="ltr" required>
                </x-field>
                <x-field name="amount" :label="__('resources.vouchers.amount')" required>
                    <input id="amount" name="amount" value="{{ old('amount', $voucher->amount) }}" @class(['form-control text-end', 'is-invalid' => $errors->has('amount')]) dir="ltr" inputmode="decimal" required data-amount>
                </x-field>
                <x-field name="payment_method_id" :label="__('resources.vouchers.method')" required>
                    <select id="payment_method_id" name="payment_method_id" class="form-select" required>
                        @foreach ($methods as $method)
                            <option value="{{ $method->id }}" @selected((int) old('payment_method_id', $voucher->payment_method_id) === $method->id)>{{ $method->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="reference" :label="__('resources.vouchers.reference')">
                    <input id="reference" name="reference" value="{{ old('reference', $voucher->reference) }}" class="form-control" maxlength="100" dir="ltr">
                </x-field>
            </div>
            <x-field name="notes" :label="__('resources.vouchers.notes')">
                <textarea id="notes" name="notes" class="form-control" rows="2" maxlength="2000">{{ old('notes', $voucher->notes) }}</textarea>
            </x-field>
        </x-panel>

        @unless ($editing)
            <x-panel :title="__('resources.vouchers.allocations')">
                <p class="form-text mt-0">{{ __('resources.vouchers.allocations_hint') }}</p>
                @if ($supplier)
                    @include('payment-vouchers.partials.allocations')
                    <p class="mt-2 mb-0 text-end">{{ __('resources.vouchers.unallocated') }}: <bdi dir="ltr" class="fw-semibold" data-unallocated></bdi></p>
                @else
                    <p class="text-muted mb-0">{{ __('resources.vouchers.choose_supplier') }}</p>
                @endif
            </x-panel>
        @endunless

        <x-sticky-actions>
            <a href="{{ $editing ? route('payment-vouchers.show', $voucher) : route('payment-vouchers.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
        </x-sticky-actions>
    </form>

    @push('scripts')
        <script type="module">
            const form = document.querySelector('[data-voucher-form]');
            const number = (value) => parseFloat(String(value).replace(/,/g, '')) || 0;

            // Display only; the server checks every allocation.
            const recalc = () => {
                const out = form.querySelector('[data-unallocated]');
                if (!out) return;
                let allocated = 0;
                form.querySelectorAll('[data-allocation]').forEach((input) => { allocated += number(input.value); });
                out.textContent = (number(form.querySelector('[data-amount]').value) - allocated).toFixed(2);
            };

            form.querySelector('[data-reload-param]')?.addEventListener('change', (event) => {
                const url = new URL(window.location.href);
                url.searchParams.set('supplier', event.target.value);
                url.searchParams.delete('purchase');
                form.removeAttribute('data-dirty-check');
                window.location.href = url.toString();
            });
            form.addEventListener('input', recalc);
            recalc();
        </script>
    @endpush
@endsection
