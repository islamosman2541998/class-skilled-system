@extends('layouts.app')

@php
    $editing = $visit->exists;
    $title = $editing ? __('visits.edit_visit') : __('visits.new_visit');
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('visits.visits') => route('site-visits.index')] + ($editing ? [$visit->number => route('site-visits.show', $visit)] : [])" />

    <form method="POST" action="{{ $editing ? route('site-visits.update', $visit) : route('site-visits.store') }}" data-dirty-check novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('visits.details')">
            @if ($lead)
                <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                <p class="mb-3">{{ __('visits.lead') }}: <a href="{{ route('leads.show', $lead) }}"><bdi dir="ltr">{{ $lead->number }}</bdi></a> · {{ $lead->displayName() }}</p>
            @endif
            <div class="form-grid">
                @if (! $lead || $lead->client_id)
                    <x-field name="client_id" :label="__('visits.client')" :required="! $lead">
                        <select id="client_id" name="client_id" data-select data-client-select data-remote="{{ route('clients.options') }}">
                            <option value=""></option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" selected>{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
                <x-field name="address" :label="__('visits.address')">
                    <input id="address" name="address" value="{{ old('address', $visit->address) }}" class="form-control" maxlength="1000">
                </x-field>
                <x-field name="map_url" :label="__('visits.map_url')">
                    <input id="map_url" type="url" name="map_url" value="{{ old('map_url', $visit->map_url) }}" @class(['form-control', 'is-invalid' => $errors->has('map_url')]) dir="ltr" maxlength="500">
                </x-field>
            </div>
        </x-panel>

        <x-panel :title="__('visits.schedule')">
            <div class="form-grid">
                <x-field name="scheduled_at" :label="__('visits.scheduled_at')" required>
                    <input id="scheduled_at" name="scheduled_at" value="{{ old('scheduled_at', $visit->scheduled_at?->format('Y-m-d H:i')) }}" @class(['form-control', 'is-invalid' => $errors->has('scheduled_at')]) data-date data-enable-time dir="ltr" required>
                </x-field>
                <x-field name="engineer_id" :label="__('visits.engineer')" required>
                    <select id="engineer_id" name="engineer_id" class="form-select" data-select required>
                        <option value=""></option>
                        @foreach ($engineers as $engineer)
                            <option value="{{ $engineer->id }}" @selected((int) old('engineer_id', $visit->engineer_id) === $engineer->id)>{{ $engineer->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                @if ($editing)
                    <x-field name="status" :label="__('visits.status')" required>
                        <select id="status" name="status" class="form-select">
                            @foreach (\App\Enums\SiteVisitStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(old('status', $visit->status->value) === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @endif
            </div>
            <x-field name="notes" :label="__('visits.notes')">
                <textarea id="notes" name="notes" class="form-control" rows="3">{{ old('notes', $visit->notes) }}</textarea>
            </x-field>
        </x-panel>

        <x-sticky-actions>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ $editing ? route('site-visits.show', $visit) : route('site-visits.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </x-sticky-actions>
    </form>
@endsection
