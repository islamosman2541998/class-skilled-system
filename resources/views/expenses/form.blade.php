@extends('layouts.app')

@php
    $editing = $expense->exists;
    $title = $editing ? __('finance.expenses.edit') : __('finance.expenses.new');
    $rate = rtrim(rtrim((string) app(\App\Settings\FinanceSettings::class)->vat_rate, '0'), '.');
    $mode = old('vat_mode', $editing ? ((float) $expense->vat_amount > 0 ? 'excluded' : 'none') : 'excluded');
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('finance.expenses.expenses') => route('expenses.index')] + ($editing ? [$expense->number => route('expenses.show', $expense)] : [])" />

    <form method="POST" action="{{ $editing ? route('expenses.update', $expense) : route('expenses.store') }}" data-dirty-check novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('finance.expenses.expense')">
            <div class="form-grid">
                <x-field name="project_id" :label="__('finance.project')" :required="! auth()->user()->seesAllRecords()" :hint="auth()->user()->seesAllRecords() ? __('finance.expenses.general_hint') : null">
                    <select id="project_id" name="project_id" class="form-select" data-select>
                        <option value="">{{ auth()->user()->seesAllRecords() ? __('finance.expenses.general') : '' }}</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected((int) old('project_id', $expense->project_id) === $project->id)>{{ $project->number }} · {{ $project->title }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="category_id" :label="__('finance.expenses.category')" required>
                    <select id="category_id" name="category_id" class="form-select" required>
                        <option value=""></option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((int) old('category_id', $expense->category_id) === $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="expense_date" :label="__('finance.expenses.date')" required>
                    <input id="expense_date" name="expense_date" value="{{ old('expense_date', $expense->expense_date?->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('expense_date')]) data-date dir="ltr" required>
                </x-field>
                <x-field name="amount" :label="__('finance.expenses.typed_amount')" required>
                    <input id="amount" name="amount" value="{{ old('amount', $expense->amount) }}" @class(['form-control text-end', 'is-invalid' => $errors->has('amount')]) dir="ltr" inputmode="decimal" required>
                </x-field>
                <x-field name="vat_mode" :label="__('finance.expenses.vat_mode')" required>
                    <select id="vat_mode" name="vat_mode" class="form-select">
                        @foreach (\App\Services\Finance\ExpenseAmounts::MODES as $option)
                            <option value="{{ $option }}" @selected($mode === $option)>{{ __('finance.expenses.vat_modes.'.$option, ['rate' => $rate]) }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="payment_method_id" :label="__('finance.receipts.method')">
                    <select id="payment_method_id" name="payment_method_id" class="form-select">
                        <option value=""></option>
                        @foreach ($methods as $method)
                            <option value="{{ $method->id }}" @selected((int) old('payment_method_id', $expense->payment_method_id) === $method->id)>{{ $method->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="paid_by" :label="__('finance.expenses.paid_by')">
                    <select id="paid_by" name="paid_by" class="form-select" data-select>
                        <option value=""></option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) old('paid_by', $expense->paid_by) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="supplier_name" :label="__('finance.expenses.supplier')">
                    <input id="supplier_name" name="supplier_name" value="{{ old('supplier_name', $expense->supplier_name) }}" class="form-control" maxlength="190">
                </x-field>
                <x-field name="supplier_invoice_no" :label="__('finance.expenses.supplier_invoice_no')">
                    <input id="supplier_invoice_no" name="supplier_invoice_no" value="{{ old('supplier_invoice_no', $expense->supplier_invoice_no) }}" class="form-control" maxlength="100" dir="ltr">
                </x-field>
            </div>
            <x-field name="description" :label="__('finance.expenses.description')">
                <textarea id="description" name="description" class="form-control" rows="2">{{ old('description', $expense->description) }}</textarea>
            </x-field>
            @unless ($editing)
                <x-field name="uploads" :label="__('finance.expenses.receipt_photo')" for="uploads-input">
                    <x-uploader name="uploads" accept="image,file" :camera="true" />
                </x-field>
            @endunless
        </x-panel>

        <x-sticky-actions>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ $editing ? route('expenses.show', $expense) : route('expenses.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </x-sticky-actions>
    </form>
@endsection
