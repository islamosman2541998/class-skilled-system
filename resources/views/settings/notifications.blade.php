@extends('layouts.app')

@section('title', __('settings.groups.notifications'))

@section('content')
    <x-page-header :title="__('settings.groups.notifications')" :breadcrumbs="[__('settings.settings') => route('settings.index')]" />

    <form method="POST" action="{{ route('settings.notifications.update') }}" data-dirty-check novalidate>
        @csrf
        @method('PUT')

        <x-panel>
            <div class="table-responsive">
                <table class="table rules-table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('notifications.type') }}</th>
                            <th scope="col">{{ __('notifications.enabled') }}</th>
                            <th scope="col">{{ __('notifications.days') }}</th>
                            <th scope="col">{{ __('notifications.recipients') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($types as $type)
                            @php
                                $rule = $settings->rule($type);
                                $key = $type->value;
                                $checkedRoles = old("rules.{$key}.roles", $rule['roles']);
                            @endphp
                            <tr>
                                <th scope="row" class="fw-medium">
                                    {{ $type->label() }}
                                    @if ($type->defaultRoles() === [])
                                        <div class="form-text">{{ __('notifications.direct_user_hint') }}</div>
                                    @endif
                                </th>
                                <td>
                                    <input type="hidden" name="rules[{{ $key }}][enabled]" value="0">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" role="switch" id="rule-{{ $key }}-enabled" name="rules[{{ $key }}][enabled]" value="1" @checked(old("rules.{$key}.enabled", $rule['enabled'])) aria-label="{{ __('notifications.enabled') }}">
                                    </div>
                                </td>
                                <td>
                                    @if ($type->usesDays())
                                        <input type="number" name="rules[{{ $key }}][days]" value="{{ old("rules.{$key}.days", $rule['days']) }}" @class(['form-control form-control-sm days-input', 'is-invalid' => $errors->has("rules.{$key}.days")]) min="1" max="365" dir="ltr" aria-label="{{ __('notifications.days') }}">
                                    @endif
                                </td>
                                <td>
                                    <div class="check-inline">
                                        @foreach ($roles as $role)
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" id="rule-{{ $key }}-{{ $role->name }}" name="rules[{{ $key }}][roles][]" value="{{ $role->name }}" @checked(in_array($role->name, $checkedRoles, true))>
                                                <label class="form-check-label" for="rule-{{ $key }}-{{ $role->name }}">{{ $role->label }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-panel>

        <div class="sticky-actions">
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ route('settings.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection
