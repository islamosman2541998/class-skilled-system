@extends('layouts.app')

@php
    $editing = $subcontract->exists;
    $title = $editing ? __('resources.subcontracts.edit') : __('resources.subcontracts.new');
    $vatOn = (bool) old('vat', $editing ? (float) $subcontract->vat_rate > 0 : true);
    $rate = $editing && (float) $subcontract->vat_rate > 0 ? (string) $subcontract->vat_rate : app(\App\Settings\FinanceSettings::class)->vat_rate;
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('resources.subcontracts.subcontracts') => route('subcontracts.index')] + ($editing ? [$subcontract->number => route('subcontracts.show', $subcontract)] : [])" />

    <form method="POST" action="{{ $editing ? route('subcontracts.update', $subcontract) : route('subcontracts.store') }}" data-dirty-check novalidate data-subcontract-form>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('resources.subcontracts.subcontract')">
            <div class="form-grid">
                <x-field name="supplier_id" :label="__('resources.subcontracts.subcontractor')" :hint="__('resources.subcontracts.subcontractor_hint')" required>
                    <select id="supplier_id" name="supplier_id" class="form-select" data-select
                        data-remote="{{ route('suppliers.options', ['type' => 'subcontractor']) }}" required>
                        <option value=""></option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((int) old('supplier_id', $subcontract->supplier_id) === $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                    @can('suppliers.create')
                        <a href="{{ route('suppliers.create', ['type' => 'subcontractor']) }}" class="form-text d-inline-block" target="_blank" rel="noopener">{{ __('resources.subcontracts.add_subcontractor') }}</a>
                    @endcan
                </x-field>
                <x-field name="project_id" :label="__('resources.subcontracts.project')" required>
                    <select id="project_id" name="project_id" class="form-select" data-select required>
                        <option value=""></option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected((int) old('project_id', $subcontract->project_id) === $project->id)>{{ $project->number }} · {{ $project->title }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="value" :label="__('resources.subcontracts.value')" required>
                    <input id="value" name="value" value="{{ old('value', $subcontract->value) }}" @class(['form-control text-end', 'is-invalid' => $errors->has('value')]) dir="ltr" inputmode="decimal" required>
                </x-field>
                <x-field name="start_date" :label="__('resources.subcontracts.start_date')" required>
                    <input id="start_date" name="start_date" value="{{ old('start_date', $subcontract->start_date?->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('start_date')]) data-date dir="ltr" required>
                </x-field>
                <x-field name="end_date" :label="__('resources.subcontracts.end_date')">
                    <input id="end_date" name="end_date" value="{{ old('end_date', $subcontract->end_date?->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('end_date')]) data-date dir="ltr">
                </x-field>
                @if ($editing)
                    <x-field name="status" :label="__('resources.subcontracts.status')">
                        <select id="status" name="status" class="form-select">
                            @foreach (\App\Enums\SubcontractStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $subcontract->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
            </div>
            <x-field name="scope" :label="__('resources.subcontracts.scope')" class="mt-3" required>
                <textarea id="scope" name="scope" rows="3" @class(['form-control', 'is-invalid' => $errors->has('scope')]) maxlength="5000" required>{{ old('scope', $subcontract->scope) }}</textarea>
            </x-field>
            <div class="form-check mt-3">
                <input type="hidden" name="vat" value="0">
                <input id="vat" type="checkbox" name="vat" value="1" class="form-check-input" @checked($vatOn)>
                <label for="vat" class="form-check-label">{{ __('resources.purchases.with_vat', ['rate' => rtrim(rtrim($rate, '0'), '.')]) }}</label>
            </div>
        </x-panel>

        <x-panel :title="__('resources.subcontracts.payments')">
            <p class="form-text mt-0">{{ __('resources.subcontracts.payments_hint') }}</p>
            @error('payments')<div class="alert alert-danger">{{ $message }}</div>@enderror
            <div class="repeater" data-rows>
                @foreach ($payments as $i => $payment)
                    @include('subcontracts.partials.payment-row', ['i' => $i, 'payment' => $payment])
                @endforeach
            </div>
            <template data-row-template>
                @include('subcontracts.partials.payment-row', ['i' => '__I__', 'payment' => []])
            </template>
            <button type="button" class="btn btn-outline btn-sm" data-row-add><x-icon name="plus" :size="16" /> {{ __('resources.subcontracts.add_payment') }}</button>
        </x-panel>

        <x-panel>
            <x-field name="notes" :label="__('resources.subcontracts.notes')">
                <textarea id="notes" name="notes" class="form-control" rows="2" maxlength="5000">{{ old('notes', $subcontract->notes) }}</textarea>
            </x-field>
        </x-panel>

        <x-sticky-actions>
            <a href="{{ $editing ? route('subcontracts.show', $subcontract) : route('subcontracts.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
        </x-sticky-actions>
    </form>

    @push('scripts')
        <script type="module">
            const form = document.querySelector('[data-subcontract-form]');
            const rows = form.querySelector('[data-rows]');
            let next = 10000;

            form.addEventListener('click', (event) => {
                if (event.target.closest('[data-row-add]')) {
                    const holder = document.createElement('div');
                    holder.innerHTML = form.querySelector('template[data-row-template]').innerHTML.replaceAll('__I__', String(next++));
                    const row = holder.firstElementChild;
                    rows.append(row);
                    window.initUi?.(row);
                }
                const remove = event.target.closest('[data-row-remove]');
                if (remove) remove.closest('[data-repeater-row]').remove();
            });
        </script>
    @endpush
@endsection
