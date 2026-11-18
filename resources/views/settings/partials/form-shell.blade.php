{{-- Shared frame of every settings form: header, form tag, sticky save bar. The fields come from the `fields` section. --}}
@extends('layouts.app')

@section('title', __('settings.groups.'.$group))

@section('content')
    <x-page-header :title="__('settings.groups.'.$group)" :breadcrumbs="[__('settings.settings') => route('settings.index')]" />

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ __('settings.validation_failed') }}</div>
    @endif

    <form method="POST" action="{{ route('settings.update', $group) }}" enctype="multipart/form-data" data-dirty-check novalidate>
        @csrf
        @method('PUT')

        @yield('fields')

        <div class="sticky-actions">
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ route('settings.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </div>
    </form>
@endsection
