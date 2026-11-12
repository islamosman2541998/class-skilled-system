@extends('layouts.auth')

@section('title', __('app.login'))

@section('content')
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <h1>{{ __('app.login') }}</h1>

        <div>
            <label for="email">{{ __('app.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" dir="ltr">
            @error('email')<div role="alert">{{ $message }}</div>@enderror
        </div>

        <div>
            <label for="password">{{ __('app.password') }}</label>
            <input id="password" type="password" name="password" required autocomplete="current-password" dir="ltr">
            @error('password')<div role="alert">{{ $message }}</div>@enderror
        </div>

        <label><input type="checkbox" name="remember" value="1"> {{ __('app.remember') }}</label>

        <button type="submit">{{ __('app.login') }}</button>
    </form>
@endsection
