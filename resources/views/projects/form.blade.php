@extends('layouts.app')

@php
    $editing = $project->exists;
    $title = $editing ? __('projects.edit_project').' '.$project->number : __('projects.new_project');
    $value = fn (string $field) => old($field, $project->{$field} instanceof \BackedEnum ? $project->{$field}->value : $project->{$field});
    $date = fn (string $field) => old($field, $project->{$field}?->format('Y-m-d'));
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('projects.projects') => route('projects.index')] + ($editing ? [$project->number => route('projects.show', $project)] : [])" />

    <form method="POST" action="{{ $editing ? route('projects.update', $project) : route('projects.store') }}" data-dirty-check novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('projects.basic')">
            @unless ($editing)
                <x-slot:tools><x-client-quick-add /></x-slot:tools>
            @endunless
            <div class="form-grid">
                @if ($editing)
                    <x-field name="client_display" :label="__('projects.client')">
                        <input id="client_display" class="form-control" value="{{ $project->client->name }}" disabled>
                    </x-field>
                @else
                    <x-field name="client_id" :label="__('projects.client')" required>
                        <select id="client_id" name="client_id" data-select data-client-select data-remote="{{ route('clients.options') }}">
                            <option value=""></option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" selected>{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field name="title_ar" :label="__('projects.title_ar')" required>
                    <input id="title_ar" name="title_ar" value="{{ $value('title_ar') }}" @class(['form-control', 'is-invalid' => $errors->has('title_ar')]) required maxlength="190" dir="rtl">
                </x-field>
                <x-field name="title_en" :label="__('projects.title_en')">
                    <input id="title_en" name="title_en" value="{{ $value('title_en') }}" class="form-control" maxlength="190" dir="ltr">
                </x-field>
                <x-field name="service_id" :label="__('projects.service')">
                    <select id="service_id" name="service_id" class="form-select">
                        <option value=""></option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((int) $value('service_id') === $service->id)>{{ $service->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="emirate_id" :label="__('projects.emirate')">
                    <select id="emirate_id" name="emirate_id" class="form-select">
                        <option value=""></option>
                        @foreach ($emirates as $emirate)
                            <option value="{{ $emirate->id }}" @selected((int) $value('emirate_id') === $emirate->id)>{{ $emirate->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="location" :label="__('projects.location')">
                    <input id="location" name="location" value="{{ $value('location') }}" class="form-control" maxlength="190">
                </x-field>
            </div>
        </x-panel>

        <x-panel :title="__('projects.contract')">
            <div class="form-grid">
                <x-field name="original_value" :label="__('projects.original_value')" :required="! $editing">
                    <input id="original_value" name="original_value" value="{{ $value('original_value') }}" @class(['form-control text-end', 'is-invalid' => $errors->has('original_value')]) dir="ltr" inputmode="decimal" @disabled($editing) required>
                </x-field>
                <x-field name="billing_method" :label="__('projects.billing_method')" required>
                    <select id="billing_method" name="billing_method" class="form-select" @disabled($editing)>
                        @foreach (\App\Enums\BillingMethod::cases() as $method)
                            <option value="{{ $method->value }}" @selected($value('billing_method') === $method->value)>{{ $method->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                @if ($editing)
                    <x-field name="status" :label="__('projects.status')" required>
                        <select id="status" name="status" class="form-select">
                            @foreach (\App\Enums\ProjectStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected($value('status') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
            </div>
        </x-panel>

        <x-panel :title="__('projects.schedule')">
            <div class="form-grid">
                <x-field name="start_date" :label="__('projects.start_date')">
                    <input id="start_date" name="start_date" value="{{ $date('start_date') }}" class="form-control" data-date dir="ltr">
                </x-field>
                <x-field name="planned_end_date" :label="__('projects.planned_end_date')">
                    <input id="planned_end_date" name="planned_end_date" value="{{ $date('planned_end_date') }}" @class(['form-control', 'is-invalid' => $errors->has('planned_end_date')]) data-date dir="ltr">
                </x-field>
                @if ($editing)
                    <x-field name="actual_end_date" :label="__('projects.actual_end_date')">
                        <input id="actual_end_date" name="actual_end_date" value="{{ $date('actual_end_date') }}" class="form-control" data-date dir="ltr">
                    </x-field>
                @endif
                <x-field name="manager_id" :label="__('projects.manager')" required>
                    <select id="manager_id" name="manager_id" class="form-select" data-select required>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) $value('manager_id') === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="members" :label="__('projects.members')" for="members">
                    <select id="members" name="members[]" class="form-select" data-select multiple>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(in_array($user->id, array_map('intval', $memberIds), true))>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
            <input type="hidden" name="members_present" value="1">
            <x-field name="description" :label="__('projects.description')">
                <textarea id="description" name="description" class="form-control" rows="3">{{ $value('description') }}</textarea>
            </x-field>
        </x-panel>

        <x-sticky-actions>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ $editing ? route('projects.show', $project) : route('projects.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </x-sticky-actions>
    </form>

    @pushOnce('scripts')
        @vite('resources/js/clients.js')
    @endPushOnce
@endsection
