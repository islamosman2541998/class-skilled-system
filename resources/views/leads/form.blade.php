@extends('layouts.app')

@php
    $editing = $lead->exists;
    $title = $editing ? __('leads.edit_lead') : __('leads.new_lead');
    $selected = fn (string $field) => (int) old($field, $lead->{$field});
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('leads.leads') => route('leads.index')] + ($editing ? [$lead->number => route('leads.show', $lead)] : [])" />

    <form method="POST" action="{{ $editing ? route('leads.update', $lead) : route('leads.store') }}" data-dirty-check novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('leads.request_data')">
            <x-slot:tools>
                <x-client-quick-add />
            </x-slot:tools>
            <div class="form-grid">
                <x-field name="client_id" :label="__('leads.existing_client')">
                    <select id="client_id" name="client_id" data-select data-client-select data-remote="{{ route('clients.options') }}">
                        <option value=""></option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" selected>{{ $client->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="source_id" :label="__('leads.source')" required>
                    <select id="source_id" name="source_id" @class(['form-select', 'is-invalid' => $errors->has('source_id')]) required>
                        <option value=""></option>
                        @foreach ($sources as $source)
                            <option value="{{ $source->id }}" @selected($selected('source_id') === $source->id)>{{ $source->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
            <p class="form-text mt-0">{{ __('leads.or_new_contact') }}</p>
            <div class="form-grid">
                <x-field name="contact_name" :label="__('leads.contact_name')">
                    <input id="contact_name" name="contact_name" value="{{ old('contact_name', $lead->contact_name) }}" @class(['form-control', 'is-invalid' => $errors->has('contact_name')]) maxlength="190">
                </x-field>
                <x-field name="contact_phone" :label="__('leads.contact_phone')">
                    <input id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $lead->contact_phone) }}" @class(['form-control', 'is-invalid' => $errors->has('contact_phone')]) dir="ltr" inputmode="tel">
                </x-field>
                <x-field name="contact_email" :label="__('leads.contact_email')">
                    <input id="contact_email" type="email" name="contact_email" value="{{ old('contact_email', $lead->contact_email) }}" @class(['form-control', 'is-invalid' => $errors->has('contact_email')]) dir="ltr">
                </x-field>
            </div>
        </x-panel>

        <x-panel :title="__('leads.project_data')">
            <div class="form-grid">
                <x-field name="service_id" :label="__('leads.service')">
                    <select id="service_id" name="service_id" class="form-select">
                        <option value=""></option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected($selected('service_id') === $service->id)>{{ $service->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="facility_type" :label="__('leads.facility_type')">
                    <input id="facility_type" name="facility_type" value="{{ old('facility_type', $lead->facility_type) }}" class="form-control" maxlength="100">
                </x-field>
                <x-field name="project_state" :label="__('leads.project_state')">
                    <input id="project_state" name="project_state" value="{{ old('project_state', $lead->project_state) }}" class="form-control" maxlength="100">
                </x-field>
                <x-field name="emirate_id" :label="__('leads.emirate')">
                    <select id="emirate_id" name="emirate_id" class="form-select" data-parent-select="area_id">
                        <option value=""></option>
                        @foreach ($emirates as $emirate)
                            <option value="{{ $emirate->id }}" @selected($selected('emirate_id') === $emirate->id)>{{ $emirate->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="area_id" :label="__('leads.area')">
                    <select id="area_id" name="area_id" class="form-select">
                        <option value=""></option>
                        @foreach ($areas as $area)
                            <option value="{{ $area->id }}" data-parent="{{ $area->parent_id }}" @selected($selected('area_id') === $area->id)>{{ $area->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="area_text" :label="__('leads.area_text')">
                    <input id="area_text" name="area_text" value="{{ old('area_text', $lead->area_text) }}" class="form-control" maxlength="190">
                </x-field>
                <x-field name="area_m2" :label="__('leads.area_m2')">
                    <input id="area_m2" name="area_m2" value="{{ old('area_m2', $lead->area_m2) }}" @class(['form-control', 'is-invalid' => $errors->has('area_m2')]) dir="ltr" inputmode="decimal">
                </x-field>
                <x-field name="expected_start" :label="__('leads.expected_start')">
                    <input id="expected_start" name="expected_start" value="{{ old('expected_start', $lead->expected_start) }}" class="form-control" maxlength="100">
                </x-field>
            </div>
            <x-field name="description" :label="__('leads.description')">
                <textarea id="description" name="description" class="form-control" rows="4">{{ old('description', $lead->description) }}</textarea>
            </x-field>
        </x-panel>

        <x-panel :title="__('leads.handling')">
            <div class="form-grid">
                @if ($editing)
                    <x-field name="status_id" :label="__('leads.status')" required>
                        <select id="status_id" name="status_id" class="form-select" required>
                            @foreach ($statuses as $status)
                                <option value="{{ $status->id }}" @selected($selected('status_id') === $status->id)>{{ $status->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field name="assigned_to" :label="__('leads.assigned_to')">
                    <select id="assigned_to" name="assigned_to" class="form-select" data-select>
                        <option value="">{{ __('leads.unassigned') }}</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected($selected('assigned_to') === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="follow_up_at" :label="__('leads.follow_up_at')">
                    <input id="follow_up_at" name="follow_up_at" value="{{ old('follow_up_at', $lead->follow_up_at?->format('Y-m-d H:i')) }}" class="form-control" data-date data-enable-time dir="ltr">
                </x-field>
            </div>
        </x-panel>

        <x-sticky-actions>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ $editing ? route('leads.show', $lead) : route('leads.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </x-sticky-actions>
    </form>

    @pushOnce('scripts')
        @vite('resources/js/clients.js')
    @endPushOnce
@endsection
