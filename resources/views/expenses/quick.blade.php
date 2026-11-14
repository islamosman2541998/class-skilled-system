{{-- Phone quick entry (spec §8.12): photo of the receipt, amount, category, project; big fields, save bar at the bottom. --}}
@extends('layouts.app')

@section('title', __('finance.expenses.quick'))

@section('content')
    <x-page-header :title="__('finance.expenses.quick')" :breadcrumbs="auth()->user()->can('expenses.view') ? [__('finance.expenses.expenses') => route('expenses.index')] : []" />

    <form method="POST" action="{{ route('expenses.quick.store') }}" class="field-form" novalidate>
        @csrf

        <x-panel>
            <x-field name="uploads" :label="__('finance.expenses.receipt_photo')" for="uploads-input">
                <x-uploader name="uploads" accept="image" :camera="true" />
            </x-field>
            <x-field name="amount" :label="__('finance.expenses.typed_amount')" required>
                <input id="amount" name="amount" value="{{ old('amount') }}" @class(['form-control form-control-lg text-end', 'is-invalid' => $errors->has('amount')]) dir="ltr" inputmode="decimal" required>
            </x-field>
            <div class="form-check mb-3">
                <input type="hidden" name="includes_vat" value="0">
                <input class="form-check-input" type="checkbox" name="includes_vat" value="1" id="includes_vat" @checked(old('includes_vat', '1') === '1')>
                <label class="form-check-label" for="includes_vat">{{ __('finance.expenses.includes_vat') }}</label>
            </div>
            <x-field name="category_id" :label="__('finance.expenses.category')" required>
                <select id="category_id" name="category_id" class="form-select form-select-lg" required>
                    <option value=""></option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((int) old('category_id') === $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="project_id" :label="__('finance.project')" :required="! auth()->user()->seesAllRecords()">
                <select id="project_id" name="project_id" class="form-select form-select-lg">
                    <option value="">{{ auth()->user()->seesAllRecords() ? __('finance.expenses.general') : '' }}</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->id }}" @selected((int) old('project_id') === $project->id)>{{ $project->number }} · {{ $project->title }}</option>
                    @endforeach
                </select>
            </x-field>
            <x-field name="description" :label="__('finance.expenses.description')">
                <input id="description" name="description" value="{{ old('description') }}" class="form-control form-control-lg" maxlength="2000">
            </x-field>
        </x-panel>

        <x-sticky-actions class="sticky-actions-mobile">
            <button type="submit" class="btn btn-primary btn-lg flex-fill">{{ __('app.save') }}</button>
        </x-sticky-actions>
    </form>
@endsection
