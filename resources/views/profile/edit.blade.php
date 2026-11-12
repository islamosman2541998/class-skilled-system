@extends('layouts.auth')

@section('title', __('app.profile'))

@section('content')
    <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('PUT')
        <label for="name">{{ __('app.full_name') }}</label>
        <input id="name" name="name" value="{{ old('name', $user->name) }}" required>
        @error('name')<div role="alert">{{ $message }}</div>@enderror

        <label for="phone">{{ __('app.phone') }}</label>
        <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" dir="ltr">
        @error('phone')<div role="alert">{{ $message }}</div>@enderror

        <label for="locale">{{ __('app.language') }}</label>
        <select id="locale" name="locale">
            <option value="ar" @selected(old('locale', $user->locale) === 'ar')>{{ __('app.arabic') }}</option>
            <option value="en" @selected(old('locale', $user->locale) === 'en')>{{ __('app.english') }}</option>
        </select>
        @error('locale')<div role="alert">{{ $message }}</div>@enderror

        <button type="submit">{{ __('app.save') }}</button>
    </form>

    <form method="POST" action="{{ route('profile.password') }}">
        @csrf
        @method('PUT')
        <label for="current_password">{{ __('app.current_password') }}</label>
        <input id="current_password" type="password" name="current_password" required autocomplete="current-password">
        @error('current_password')<div role="alert">{{ $message }}</div>@enderror

        <label for="password">{{ __('app.new_password') }}</label>
        <input id="password" type="password" name="password" required autocomplete="new-password">
        @error('password')<div role="alert">{{ $message }}</div>@enderror

        <label for="password_confirmation">{{ __('app.confirm_password') }}</label>
        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

        <button type="submit">{{ __('app.change_password') }}</button>
    </form>
@endsection
