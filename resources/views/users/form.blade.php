@extends('layouts.app')

@php
    $editing = $user->exists;
    $title = $editing ? __('users.edit_user') : __('users.new_user');
    $checkedRoles = old('roles', $assigned);
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('users.users') => route('users.index')]" />

    <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" data-dirty-check novalidate>
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="row g-3">
            <div class="col-lg-7">
                <x-panel :title="__('users.account')">
                    <div class="form-grid">
                        <x-field name="name" :label="__('app.full_name')" required>
                            <input id="name" name="name" value="{{ old('name', $user->name) }}" @class(['form-control', 'is-invalid' => $errors->has('name')]) required maxlength="120">
                        </x-field>

                        <x-field name="email" :label="__('app.email')" required>
                            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}" @class(['form-control', 'is-invalid' => $errors->has('email')]) required dir="ltr" autocomplete="off">
                        </x-field>

                        <x-field name="phone" :label="__('app.phone')">
                            <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" @class(['form-control', 'is-invalid' => $errors->has('phone')]) dir="ltr" inputmode="tel">
                        </x-field>

                        <x-field name="locale" :label="__('app.language')" required>
                            <select id="locale" name="locale" class="form-select">
                                <option value="ar" @selected(old('locale', $user->locale) === 'ar')>{{ __('app.arabic') }}</option>
                                <option value="en" @selected(old('locale', $user->locale) === 'en')>{{ __('app.english') }}</option>
                            </select>
                        </x-field>

                        @unless ($editing)
                            <x-field name="password" :label="__('app.password')" :hint="__('app.password_hint')" required>
                                <input id="password" type="password" name="password" @class(['form-control', 'is-invalid' => $errors->has('password')]) required dir="ltr" autocomplete="new-password">
                            </x-field>

                            <x-field name="password_confirmation" :label="__('app.confirm_password')" required>
                                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required dir="ltr" autocomplete="new-password">
                            </x-field>
                        @endunless
                    </div>
                </x-panel>
            </div>

            <div class="col-lg-5">
                <x-panel :title="__('users.access')">
                    <x-field name="roles" :label="__('users.roles')" for="role-{{ $roles->first()?->name }}">
                        <div class="check-list">
                            @foreach ($roles as $role)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}" id="role-{{ $role->name }}" @checked(in_array($role->name, $checkedRoles, true))>
                                    <label class="form-check-label" for="role-{{ $role->name }}">{{ $role->label }}</label>
                                </div>
                            @endforeach
                        </div>
                    </x-field>

                    <x-field name="is_active" :label="__('users.status')" for="is_active">
                        <input type="hidden" name="is_active" value="0">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_active" value="1" id="is_active" @checked(old('is_active', $user->is_active))>
                            <label class="form-check-label" for="is_active">{{ __('users.active') }}</label>
                        </div>
                    </x-field>
                </x-panel>
            </div>
        </div>

        <div class="sticky-actions">
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ route('users.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </div>
    </form>

    @if ($editing)
        <div class="row g-3 mt-1">
            <div class="col-lg-7">
                <x-panel :title="__('users.reset_password')">
                    <form method="POST" action="{{ route('users.reset-password', $user) }}">
                        @csrf
                        @method('PUT')
                        <div class="form-grid">
                            <x-field name="password" :label="__('app.new_password')" :hint="__('app.password_hint')" required>
                                <input id="password" type="password" name="password" @class(['form-control', 'is-invalid' => $errors->has('password')]) required dir="ltr" autocomplete="new-password">
                            </x-field>

                            <x-field name="password_confirmation" :label="__('app.confirm_password')" required>
                                <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required dir="ltr" autocomplete="new-password">
                            </x-field>
                        </div>
                        <button type="submit" class="btn btn-outline">{{ __('users.reset_password') }}</button>
                    </form>
                </x-panel>
            </div>
        </div>
    @endif
@endsection
