@extends('layouts.app')

@section('title', __('app.profile'))

@section('content')
    <x-page-header :title="__('app.profile')" />

    <div class="row g-3">
        <div class="col-lg-6">
            <x-panel :title="__('app.profile')">
                <form method="POST" action="{{ route('profile.update') }}" data-dirty-check>
                    @csrf
                    @method('PUT')

                    <x-field name="name" :label="__('app.full_name')" required>
                        <input id="name" name="name" value="{{ old('name', $user->name) }}" @class(['form-control', 'is-invalid' => $errors->has('name')]) required>
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

                    <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                </form>
            </x-panel>
        </div>

        <div class="col-lg-6">
            <x-panel :title="__('app.change_password')">
                <form method="POST" action="{{ route('profile.password') }}">
                    @csrf
                    @method('PUT')

                    <x-field name="current_password" :label="__('app.current_password')" required>
                        <input id="current_password" type="password" name="current_password" @class(['form-control', 'is-invalid' => $errors->has('current_password')]) required autocomplete="current-password" dir="ltr">
                    </x-field>

                    <x-field name="password" :label="__('app.new_password')" :hint="__('app.password_hint')" required>
                        <input id="password" type="password" name="password" @class(['form-control', 'is-invalid' => $errors->has('password')]) required autocomplete="new-password" dir="ltr">
                    </x-field>

                    <x-field name="password_confirmation" :label="__('app.confirm_password')" required>
                        <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required autocomplete="new-password" dir="ltr">
                    </x-field>

                    <button type="submit" class="btn btn-primary">{{ __('app.change_password') }}</button>
                </form>
            </x-panel>
        </div>
    </div>
@endsection
