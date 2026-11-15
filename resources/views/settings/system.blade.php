@extends('settings.partials.form-shell')

@section('fields')
    <x-panel :title="__('settings.groups.system')">
        <div class="form-grid">
            <x-field name="session_idle_minutes" :label="__('settings.system.session_idle_minutes')" :hint="__('settings.system.session_idle_minutes_hint')" required>
                <input id="session_idle_minutes" type="number" name="session_idle_minutes" value="{{ old('session_idle_minutes', $settings->session_idle_minutes) }}" @class(['form-control', 'is-invalid' => $errors->has('session_idle_minutes')]) required min="5" max="1440" dir="ltr">
            </x-field>
            <x-field name="trash_retention_days" :label="__('settings.system.trash_retention_days')" :hint="__('settings.system.trash_retention_days_hint')">
                <input id="trash_retention_days" type="number" name="trash_retention_days" value="{{ old('trash_retention_days', $settings->trash_retention_days) }}" @class(['form-control', 'is-invalid' => $errors->has('trash_retention_days')]) min="1" max="3650" dir="ltr">
            </x-field>
            <x-field name="public_link_hours" :label="__('settings.system.public_link_hours')" :hint="__('settings.system.public_link_hours_hint')" required>
                <input id="public_link_hours" type="number" name="public_link_hours" value="{{ old('public_link_hours', $settings->public_link_hours) }}" @class(['form-control', 'is-invalid' => $errors->has('public_link_hours')]) required min="1" max="720" dir="ltr">
            </x-field>
        </div>
        <fieldset class="mt-3">
            <legend class="form-label">{{ __('settings.system.weekly_days_off') }}</legend>
            @php($daysOff = array_map('intval', old('weekly_days_off', $settings->weekly_days_off ?? [])))
            <div class="d-flex flex-wrap gap-3">
                @foreach (__('site.weekdays') as $number => $name)
                    <div class="form-check">
                        <input id="weekly_days_off_{{ $number }}" type="checkbox" name="weekly_days_off[]" value="{{ $number }}" class="form-check-input" @checked(in_array($number, $daysOff, true))>
                        <label for="weekly_days_off_{{ $number }}" class="form-check-label">{{ $name }}</label>
                    </div>
                @endforeach
            </div>
            <div class="form-text">{{ __('settings.system.weekly_days_off_hint') }}</div>
            @error('weekly_days_off')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
            @error('weekly_days_off.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </fieldset>
    </x-panel>
@endsection
