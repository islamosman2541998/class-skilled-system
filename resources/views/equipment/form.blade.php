@extends('layouts.app')

@php
    $editing = $equipment->exists;
    $title = $editing ? __('resources.equipment.edit') : __('resources.equipment.new');
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('resources.equipment.equipment') => route('equipment.index')] + ($editing ? [$equipment->name => route('equipment.show', $equipment)] : [])" />

    <form method="POST" action="{{ $editing ? route('equipment.update', $equipment) : route('equipment.store') }}" data-dirty-check novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel>
            <div class="form-grid">
                <x-field name="name_ar" :label="__('resources.suppliers.name_ar')" required>
                    <input id="name_ar" name="name_ar" value="{{ old('name_ar', $equipment->name_ar) }}" @class(['form-control', 'is-invalid' => $errors->has('name_ar')]) maxlength="190" required dir="rtl">
                </x-field>
                <x-field name="name_en" :label="__('resources.suppliers.name_en')">
                    <input id="name_en" name="name_en" value="{{ old('name_en', $equipment->name_en) }}" @class(['form-control', 'is-invalid' => $errors->has('name_en')]) maxlength="190" dir="ltr">
                </x-field>
                <x-field name="type_id" :label="__('resources.equipment.type')">
                    <select id="type_id" name="type_id" class="form-select">
                        <option value=""></option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}" @selected((int) old('type_id', $equipment->type_id) === $type->id)>{{ $type->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="ownership" :label="__('resources.equipment.ownership')" required>
                    <select id="ownership" name="ownership" class="form-select" required>
                        @foreach (\App\Enums\EquipmentOwnership::cases() as $ownership)
                            <option value="{{ $ownership->value }}" @selected(old('ownership', $equipment->ownership?->value) === $ownership->value)>{{ $ownership->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="daily_cost" :label="__('resources.equipment.daily_cost')" required>
                    <input id="daily_cost" name="daily_cost" value="{{ old('daily_cost', $equipment->daily_cost) }}" @class(['form-control text-end', 'is-invalid' => $errors->has('daily_cost')]) dir="ltr" inputmode="decimal" required>
                </x-field>
            </div>
            <x-field name="notes" :label="__('resources.suppliers.notes')" class="mt-3">
                <textarea id="notes" name="notes" rows="2" class="form-control" maxlength="2000">{{ old('notes', $equipment->notes) }}</textarea>
            </x-field>
            <div class="form-check mt-3">
                <input type="hidden" name="is_active" value="0">
                <input id="is_active" type="checkbox" name="is_active" value="1" class="form-check-input" @checked((bool) old('is_active', $equipment->is_active))>
                <label for="is_active" class="form-check-label">{{ __('resources.suppliers.is_active') }}</label>
            </div>
        </x-panel>

        <x-sticky-actions>
            <a href="{{ $editing ? route('equipment.show', $equipment) : route('equipment.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
        </x-sticky-actions>
    </form>
@endsection
