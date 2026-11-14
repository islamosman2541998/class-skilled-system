@extends('layouts.app')

@php
    $editing = $invoice->exists;
    $title = $editing ? __('finance.invoices.edit') : __('finance.invoices.new');
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('finance.invoices.invoices') => route('invoices.index')] + ($editing ? [__('finance.invoices.draft_title') => route('invoices.show', $invoice)] : [])" />

    <form method="POST" action="{{ $editing ? route('invoices.update', $invoice) : route('invoices.store') }}" data-dirty-check novalidate data-invoice-form>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('finance.invoices.invoice')">
            <div class="form-grid">
                <x-field name="project_id" :label="__('finance.project')" required>
                    <select id="project_id" name="project_id" class="form-select" data-select required data-project-select>
                        <option value=""></option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" data-vat="{{ $project->vat_rate }}" @selected((int) old('project_id', $invoice->project_id) === $project->id)>{{ $project->number }} · {{ $project->title }} · {{ $project->client->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="issue_date" :label="__('finance.invoices.issue_date')" required>
                    <input id="issue_date" name="issue_date" value="{{ old('issue_date', $invoice->issue_date?->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('issue_date')]) data-date dir="ltr" required>
                </x-field>
                <x-field name="due_date" :label="__('finance.invoices.due_date')" required>
                    <input id="due_date" name="due_date" value="{{ old('due_date', $invoice->due_date?->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('due_date')]) data-date dir="ltr" required>
                </x-field>
            </div>
        </x-panel>

        <x-panel :title="__('finance.items')">
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
                    <div>{{ __('finance.subtotal') }}: <bdi dir="ltr" data-subtotal></bdi></div>
                    <div>{{ __('finance.vat') }} <bdi dir="ltr" data-vat-rate></bdi>: <bdi dir="ltr" data-vat></bdi></div>
                    <div class="fw-semibold">{{ __('finance.total') }}: <bdi dir="ltr" data-total></bdi></div>
                </dl>
            </div>
        </x-panel>

        <x-panel :title="__('finance.invoices.terms')">
            <div class="form-grid">
                <x-field name="terms_ar" :label="__('finance.invoices.terms_ar')">
                    <textarea id="terms_ar" name="terms_ar" class="form-control" rows="4" dir="rtl">{{ old('terms_ar', $invoice->terms_ar) }}</textarea>
                </x-field>
                <x-field name="terms_en" :label="__('finance.invoices.terms_en')">
                    <textarea id="terms_en" name="terms_en" class="form-control" rows="4" dir="ltr">{{ old('terms_en', $invoice->terms_en) }}</textarea>
                </x-field>
            </div>
            <x-field name="notes" :label="__('finance.notes')">
                <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $invoice->notes) }}</textarea>
            </x-field>
        </x-panel>

        <x-sticky-actions>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ $editing ? route('invoices.show', $invoice) : route('invoices.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </x-sticky-actions>
    </form>

    @push('scripts')
        <script type="module">
            const form = document.querySelector('[data-invoice-form]');
            const rows = form.querySelector('[data-rows]');
            const project = form.querySelector('[data-project-select]');
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
                const rate = number(project.selectedOptions[0]?.dataset.vat ?? 0);
                const vat = Math.round(subtotal * rate) / 100;
                form.querySelector('[data-subtotal]').textContent = subtotal.toFixed(2);
                form.querySelector('[data-vat-rate]').textContent = '(' + rate + '%)';
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
