@extends('layouts.app')

@php
    $editing = $purchase->exists;
    $title = $editing ? __('resources.purchases.edit') : __('resources.purchases.new');
    $vatOn = (bool) old('vat', $editing ? (float) $purchase->vat_rate > 0 : true);
    $rate = $editing && (float) $purchase->vat_rate > 0 ? (string) $purchase->vat_rate : app(\App\Settings\FinanceSettings::class)->vat_rate;
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('resources.purchases.purchases') => route('purchases.index')] + ($editing ? [$purchase->number => route('purchases.show', $purchase)] : [])" />

    <form method="POST" action="{{ $editing ? route('purchases.update', $purchase) : route('purchases.store') }}" data-dirty-check novalidate data-purchase-form data-rate="{{ $rate }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('resources.purchases.purchase')">
            <div class="form-grid">
                <x-field name="supplier_id" :label="__('resources.purchases.supplier')" required>
                    <select id="supplier_id" name="supplier_id" class="form-select" data-select required>
                        <option value=""></option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $purchase->supplier_id) === $supplier->id)>{{ $supplier->name }} · {{ $supplier->type->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="project_id" :label="__('resources.purchases.project')" required>
                    <select id="project_id" name="project_id" class="form-select" data-select required>
                        <option value=""></option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected((int) old('project_id', $purchase->project_id) === $project->id)>{{ $project->number }} · {{ $project->title }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="purchase_date" :label="__('resources.purchases.date')" required>
                    <input id="purchase_date" name="purchase_date" value="{{ old('purchase_date', $purchase->purchase_date?->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('purchase_date')]) data-date dir="ltr" required>
                </x-field>
                <x-field name="supplier_invoice_no" :label="__('resources.purchases.supplier_invoice_no')" required>
                    <input id="supplier_invoice_no" name="supplier_invoice_no" value="{{ old('supplier_invoice_no', $purchase->supplier_invoice_no) }}" @class(['form-control', 'is-invalid' => $errors->has('supplier_invoice_no')]) maxlength="60" dir="ltr" required>
                </x-field>
            </div>
            <div class="form-check mt-3">
                <input type="hidden" name="vat" value="0">
                <input id="vat" type="checkbox" name="vat" value="1" class="form-check-input" @checked($vatOn) data-vat-switch>
                <label for="vat" class="form-check-label">{{ __('resources.purchases.with_vat', ['rate' => rtrim(rtrim($rate, '0'), '.')]) }}</label>
            </div>
        </x-panel>

        <x-panel :title="__('resources.purchases.items')">
            <div class="repeater" data-rows>
                @foreach ($items as $i => $item)
                    @include('invoices.partials.item-row', ['i' => $i, 'item' => $item])
                @endforeach
            </div>
            <template data-row-template>
                @include('invoices.partials.item-row', ['i' => '__I__', 'item' => ['quantity' => '1', 'unit_price' => '0']])
            </template>
            @error('items')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            <div class="payment-template-footer">
                <button type="button" class="btn btn-outline btn-sm" data-row-add><x-icon name="plus" :size="16" /> {{ __('finance.add_item') }}</button>
                <dl class="ms-auto mb-0 text-end">
                    <div>{{ __('resources.purchases.subtotal') }}: <bdi dir="ltr" data-subtotal></bdi></div>
                    <div>{{ __('resources.purchases.vat') }}: <bdi dir="ltr" data-vat></bdi></div>
                    <div class="fw-semibold">{{ __('resources.purchases.total') }}: <bdi dir="ltr" data-total></bdi></div>
                </dl>
            </div>
        </x-panel>

        <x-panel>
            <x-field name="notes" :label="__('resources.purchases.notes')">
                <textarea id="notes" name="notes" class="form-control" rows="2" maxlength="5000">{{ old('notes', $purchase->notes) }}</textarea>
            </x-field>
            @unless ($editing)
                <x-field name="uploads" :label="__('resources.purchases.attachment')" for="uploads-input">
                    <x-uploader name="uploads" accept="image,file" :camera="true" />
                </x-field>
            @endunless
        </x-panel>

        <x-sticky-actions>
            <a href="{{ $editing ? route('purchases.show', $purchase) : route('purchases.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
        </x-sticky-actions>
    </form>

    @push('scripts')
        <script type="module">
            const form = document.querySelector('[data-purchase-form]');
            const rows = form.querySelector('[data-rows]');
            const vatSwitch = form.querySelector('[data-vat-switch]');
            let next = 10000;
            const number = (value) => parseFloat(String(value).replace(/,/g, '')) || 0;

            // Display only; the server computes the stored totals.
            const recalc = () => {
                let subtotal = 0;
                rows.querySelectorAll('[data-repeater-row]').forEach((row) => {
                    const line = Math.round(number(row.querySelector('[data-qty]').value) * number(row.querySelector('[data-price]').value) * 100) / 100;
                    row.querySelector('[data-line]').textContent = line.toFixed(2);
                    subtotal += line;
                });
                const rate = vatSwitch.checked ? number(form.dataset.rate) : 0;
                const vat = Math.round(subtotal * rate) / 100;
                form.querySelector('[data-subtotal]').textContent = subtotal.toFixed(2);
                form.querySelector('[data-vat]').textContent = vat.toFixed(2);
                form.querySelector('[data-total]').textContent = (subtotal + vat).toFixed(2);
            };

            form.addEventListener('input', recalc);
            form.addEventListener('change', recalc);
            form.addEventListener('click', (event) => {
                if (event.target.closest('[data-row-add]')) {
                    rows.insertAdjacentHTML('beforeend', form.querySelector('template[data-row-template]').innerHTML.replaceAll('__I__', String(next++)));
                }
                if (event.target.closest('[data-row-remove]') && rows.children.length > 1) {
                    event.target.closest('[data-repeater-row]').remove();
                }
                recalc();
            });
            recalc();
        </script>
    @endpush
@endsection
