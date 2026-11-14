@extends('layouts.app')

@php
    $editing = $receipt->exists;
    $title = $editing ? __('finance.receipts.edit') : __('finance.receipts.new');
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('finance.receipts.receipts') => route('receipts.index')] + ($editing ? [$receipt->number => route('receipts.show', $receipt)] : [])" />

    <form method="POST" action="{{ $editing ? route('receipts.update', $receipt) : route('receipts.store') }}" data-dirty-check novalidate data-receipt-form>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('finance.receipts.receipt')">
            <div class="form-grid">
                @if ($editing)
                    <div><span class="form-label d-block">{{ __('finance.client') }}</span>{{ $receipt->client->name }}</div>
                    <div><span class="form-label d-block">{{ __('finance.project') }}</span>{{ $receipt->project ? $receipt->project->number.' · '.$receipt->project->title : '' }}</div>
                @else
                    <x-field name="client_id" :label="__('finance.client')" required>
                        {{-- Choosing a client reloads the page with that client's projects and open invoices. --}}
                        <select id="client_id" name="client_id" data-select data-remote="{{ route('clients.options') }}" data-reload-param="client" required>
                            <option value=""></option>
                            @if ($client)
                                <option value="{{ $client->id }}" selected>{{ $client->name }}</option>
                            @endif
                        </select>
                    </x-field>
                    <x-field name="project_id" :label="__('finance.project')" :hint="__('finance.receipts.project_hint')">
                        <select id="project_id" name="project_id" class="form-select">
                            <option value=""></option>
                            @foreach ($projects as $project)
                                <option value="{{ $project->id }}" @selected((int) old('project_id', $receipt->project_id) === $project->id)>{{ $project->number }} · {{ $project->title }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field name="receipt_date" :label="__('finance.receipts.date')" required>
                    <input id="receipt_date" name="receipt_date" value="{{ old('receipt_date', $receipt->receipt_date?->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('receipt_date')]) data-date dir="ltr" required>
                </x-field>
                <x-field name="amount" :label="__('finance.receipts.amount')" required>
                    <input id="amount" name="amount" value="{{ old('amount', $receipt->amount) }}" @class(['form-control text-end', 'is-invalid' => $errors->has('amount')]) dir="ltr" inputmode="decimal" required data-amount>
                </x-field>
                <x-field name="payment_method_id" :label="__('finance.receipts.method')" required>
                    <select id="payment_method_id" name="payment_method_id" class="form-select" required data-method>
                        @foreach ($methods as $method)
                            <option value="{{ $method->id }}" data-code="{{ $method->code }}" @selected((int) old('payment_method_id', $receipt->payment_method_id) === $method->id)>{{ $method->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="reference" :label="__('finance.receipts.reference')">
                    <input id="reference" name="reference" value="{{ old('reference', $receipt->reference) }}" class="form-control" maxlength="100" dir="ltr">
                </x-field>
                <x-field name="cheque_date" :label="__('finance.receipts.cheque_date')" data-cheque-field>
                    <input id="cheque_date" name="cheque_date" value="{{ old('cheque_date', $receipt->cheque_date?->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('cheque_date')]) data-date dir="ltr">
                </x-field>
                <x-field name="received_by" :label="__('finance.receipts.received_by')" required>
                    <select id="received_by" name="received_by" class="form-select" data-select required>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) old('received_by', $receipt->received_by) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
            <x-field name="notes" :label="__('finance.notes')">
                <textarea id="notes" name="notes" class="form-control" rows="2">{{ old('notes', $receipt->notes) }}</textarea>
            </x-field>
        </x-panel>

        @unless ($editing)
            <x-panel :title="__('finance.receipts.allocations')">
                <p class="form-text mt-0">{{ __('finance.receipts.allocations_hint') }}</p>
                @if ($client)
                    @include('receipts.partials.allocations')
                    <p class="mt-2 mb-0 text-end">{{ __('finance.receipts.unallocated') }}: <bdi dir="ltr" class="fw-semibold" data-unallocated></bdi></p>
                @else
                    <p class="text-muted mb-0">{{ __('finance.receipts.choose_client') }}</p>
                @endif
            </x-panel>
        @endunless

        <x-sticky-actions>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ $editing ? route('receipts.show', $receipt) : route('receipts.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </x-sticky-actions>
    </form>

    @push('scripts')
        <script type="module">
            const form = document.querySelector('[data-receipt-form]');
            const number = (value) => parseFloat(String(value).replace(/,/g, '')) || 0;

            // Display only; the server checks every allocation.
            const recalc = () => {
                const out = form.querySelector('[data-unallocated]');
                if (!out) return;
                let allocated = 0;
                form.querySelectorAll('[data-allocation]').forEach((input) => { allocated += number(input.value); });
                out.textContent = (number(form.querySelector('[data-amount]').value) - allocated).toFixed(2);
            };

            // The cheque date matters only for the cheque method.
            const method = form.querySelector('[data-method]');
            const toggleCheque = () => {
                const field = form.querySelector('[data-cheque-field]');
                if (field) field.hidden = method.selectedOptions[0]?.dataset.code !== 'cheque';
            };

            form.querySelector('[data-reload-param]')?.addEventListener('change', (event) => {
                const url = new URL(window.location.href);
                url.searchParams.set('client', event.target.value);
                url.searchParams.delete('invoice');
                form.removeAttribute('data-dirty-check');
                window.location.href = url.toString();
            });
            form.addEventListener('input', recalc);
            method.addEventListener('change', toggleCheque);
            recalc();
            toggleCheque();
        </script>
    @endpush
@endsection
