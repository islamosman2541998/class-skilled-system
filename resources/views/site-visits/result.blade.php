{{-- One-step result form for the engineer on site: big fields, camera, save bar at the bottom (spec §4.4). --}}
@extends('layouts.app')

@section('title', __('visits.record_result'))

@section('content')
    <x-page-header :title="__('visits.record_result')" :breadcrumbs="[__('visits.my_visits') => route('my-visits'), $visit->number => route('site-visits.show', $visit)]" />

    <form method="POST" action="{{ route('site-visits.result.store', $visit) }}" class="field-form" data-dirty-check novalidate>
        @csrf
        @method('PUT')

        <x-panel :title="$visit->client->name">
            <x-field name="status" :label="__('visits.result')" for="result-done" required>
                <div class="btn-group w-100" role="group">
                    <input type="radio" class="btn-check" name="status" id="result-done" value="done" @checked(old('status', 'done') === 'done')>
                    <label class="btn btn-outline btn-lg" for="result-done">{{ __('visits.statuses.done') }}</label>
                    <input type="radio" class="btn-check" name="status" id="result-cancelled" value="cancelled" @checked(old('status') === 'cancelled')>
                    <label class="btn btn-outline btn-lg" for="result-cancelled">{{ __('visits.statuses.cancelled') }}</label>
                </div>
            </x-field>
            <x-field name="measurements" :label="__('visits.measurements')">
                <textarea id="measurements" name="measurements" class="form-control form-control-lg" rows="3">{{ old('measurements', $visit->measurements) }}</textarea>
            </x-field>
            <x-field name="notes" :label="__('visits.notes')">
                <textarea id="notes" name="notes" class="form-control form-control-lg" rows="4">{{ old('notes', $visit->notes) }}</textarea>
            </x-field>
            <x-field name="uploads" :label="__('visits.photos')" for="uploads-input">
                <x-uploader name="uploads" accept="image,video" :camera="true" />
            </x-field>
        </x-panel>

        <x-sticky-actions class="sticky-actions-mobile">
            <button type="submit" class="btn btn-primary btn-lg flex-fill">{{ __('app.save') }}</button>
        </x-sticky-actions>
    </form>
@endsection
