@extends('layouts.app')

@php
    $editing = $worker->exists;
    $title = $editing ? __('resources.workers.edit') : __('resources.workers.new');
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('resources.workers.workers') => route('workers.index')]" />

    <form method="POST" action="{{ $editing ? route('workers.update', $worker) : route('workers.store') }}" data-dirty-check novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel>
            <div class="form-grid">
                <x-field name="name_ar" :label="__('resources.suppliers.name_ar')" required>
                    <input id="name_ar" name="name_ar" value="{{ old('name_ar', $worker->name_ar) }}" @class(['form-control', 'is-invalid' => $errors->has('name_ar')]) maxlength="190" required dir="rtl">
                </x-field>
                <x-field name="name_en" :label="__('resources.suppliers.name_en')">
                    <input id="name_en" name="name_en" value="{{ old('name_en', $worker->name_en) }}" @class(['form-control', 'is-invalid' => $errors->has('name_en')]) maxlength="190" dir="ltr">
                </x-field>
                <x-field name="trade_id" :label="__('resources.workers.trade')">
                    <select id="trade_id" name="trade_id" class="form-select">
                        <option value=""></option>
                        @foreach ($trades as $trade)
                            <option value="{{ $trade->id }}" @selected((int) old('trade_id', $worker->trade_id) === $trade->id)>{{ $trade->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="daily_wage" :label="__('resources.workers.daily_wage')" :hint="__('resources.workers.daily_wage_hint')" required>
                    <input id="daily_wage" name="daily_wage" value="{{ old('daily_wage', $worker->daily_wage) }}" @class(['form-control text-end', 'is-invalid' => $errors->has('daily_wage')]) dir="ltr" inputmode="decimal" required>
                </x-field>
                <x-field name="phone" :label="__('app.phone')">
                    <input id="phone" name="phone" value="{{ old('phone', $worker->phone) }}" @class(['form-control', 'is-invalid' => $errors->has('phone')]) dir="ltr" inputmode="tel">
                </x-field>
            </div>
            <div class="d-flex flex-wrap gap-4 mt-3">
                <div class="form-check">
                    <input type="hidden" name="is_company" value="0">
                    <input id="is_company" type="checkbox" name="is_company" value="1" class="form-check-input" @checked((bool) old('is_company', $worker->is_company))>
                    <label for="is_company" class="form-check-label">{{ __('resources.workers.company') }}</label>
                </div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input id="is_active" type="checkbox" name="is_active" value="1" class="form-check-input" @checked((bool) old('is_active', $worker->is_active))>
                    <label for="is_active" class="form-check-label">{{ __('resources.suppliers.is_active') }}</label>
                </div>
            </div>
        </x-panel>

        <x-sticky-actions>
            <a href="{{ route('workers.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
        </x-sticky-actions>
    </form>
@endsection
