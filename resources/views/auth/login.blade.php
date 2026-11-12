@extends('layouts.auth')

@section('title', __('app.login'))

@section('content')
    <form method="POST" action="{{ route('login.store') }}" novalidate>
        @csrf
        <h1 class="h2 mb-3">{{ __('app.login') }}</h1>

        <x-field name="email" :label="__('app.email')">
            <input id="email" type="email" name="email" value="{{ old('email') }}" @class(['form-control', 'is-invalid' => $errors->has('email')]) required autofocus autocomplete="username" dir="ltr">
        </x-field>

        <x-field name="password" :label="__('app.password')">
            <input id="password" type="password" name="password" @class(['form-control', 'is-invalid' => $errors->has('password')]) required autocomplete="current-password" dir="ltr">
        </x-field>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" value="1" id="remember">
            <label class="form-check-label" for="remember">{{ __('app.remember') }}</label>
        </div>

        <button type="submit" class="btn btn-primary w-100">{{ __('app.login') }}</button>
    </form>
@endsection
