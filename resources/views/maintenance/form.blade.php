@extends('layouts.app')

@php
    $editing = $maintenance->exists;
    $title = $editing ? __('maintenance.edit_request') : __('maintenance.new_request');
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('maintenance.maintenance') => route('maintenance.index')] + ($editing ? [$maintenance->number => route('maintenance.show', $maintenance)] : [])" />

    <form method="POST" action="{{ $editing ? route('maintenance.update', $maintenance) : route('maintenance.store') }}" data-dirty-check novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('maintenance.details')">
            <div class="form-grid">
                <x-field name="project_id" :label="__('maintenance.project')" required>
                    <select id="project_id" name="project_id" class="form-select" data-select required>
                        <option value=""></option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}" @selected((int) old('project_id', $maintenance->project_id) === $project->id)>{{ $project->number }} · {{ $project->title }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="request_date" :label="__('maintenance.request_date')" required>
                    <input id="request_date" name="request_date" value="{{ old('request_date', $maintenance->request_date?->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('request_date')]) data-date dir="ltr" required>
                </x-field>
                <x-field name="assigned_to" :label="__('maintenance.assigned_to')">
                    <select id="assigned_to" name="assigned_to" class="form-select" data-select>
                        <option value="">{{ __('maintenance.unassigned') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) old('assigned_to', $maintenance->assigned_to) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="cost" :label="__('maintenance.cost')">
                    <input id="cost" name="cost" value="{{ old('cost', $editing ? $maintenance->cost : '') }}" @class(['form-control text-end', 'is-invalid' => $errors->has('cost')]) dir="ltr" inputmode="decimal">
                </x-field>
                @if ($editing)
                    <x-field name="status" :label="__('maintenance.status')" required>
                        <select id="status" name="status" class="form-select">
                            @foreach (\App\Enums\MaintenanceStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $maintenance->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
            </div>
            <x-field name="description" :label="__('maintenance.description')" required>
                <textarea id="description" name="description" @class(['form-control', 'is-invalid' => $errors->has('description')]) rows="3" required>{{ old('description', $maintenance->description) }}</textarea>
            </x-field>
            @if ($editing)
                <div class="mb-3">
                    <input type="hidden" name="in_warranty" value="0">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="in_warranty" value="1" id="in_warranty" @checked((bool) old('in_warranty', $maintenance->in_warranty))>
                        <label class="form-check-label" for="in_warranty">{{ __('maintenance.in_warranty') }}</label>
                    </div>
                    <p class="form-text mb-0">{{ __('maintenance.warranty_hint') }}</p>
                </div>
            @endif
            <x-field name="notes" :label="__('maintenance.notes')">
                <textarea id="notes" name="notes" @class(['form-control', 'is-invalid' => $errors->has('notes')]) rows="2">{{ old('notes', $maintenance->notes) }}</textarea>
            </x-field>
        </x-panel>

        <x-sticky-actions>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ $editing ? route('maintenance.show', $maintenance) : route('maintenance.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </x-sticky-actions>
    </form>
@endsection
