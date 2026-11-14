@extends('layouts.app')

@section('title', __('settings.groups.integration'))

@section('content')
    <x-page-header :title="__('settings.groups.integration')" :breadcrumbs="[__('settings.settings') => route('settings.index')]" />

    @if (session('generated_key'))
        <div class="alert alert-warning">
            <div class="fw-semibold mb-1">{{ __('settings.integration.key_once') }}</div>
            <code dir="ltr" class="d-block user-select-all">{{ session('generated_key') }}</code>
        </div>
    @endif

    <x-panel :title="__('settings.integration.key')">
        <p class="mb-2">{{ $settings->api_key_hash ? __('settings.integration.key_set') : __('settings.integration.key_missing') }}</p>
        <p class="form-text">{{ __('settings.integration.api_endpoint') }}: <bdi dir="ltr">{{ route('api.website.leads') }}</bdi></p>
        <form method="POST" action="{{ route('settings.integration.key') }}" data-confirm="{{ __('settings.integration.generate_confirm') }}" data-confirm-danger>
            @csrf
            <button type="submit" class="btn btn-outline">{{ __('settings.integration.generate_key') }}</button>
        </form>
    </x-panel>

    <x-panel :title="__('settings.groups.integration')">
        <form method="POST" action="{{ route('settings.integration.update') }}" novalidate>
            @csrf
            @method('PUT')
            <x-field name="website_url" :label="__('settings.integration.website_url')" :hint="__('settings.integration.website_url_hint')">
                <input id="website_url" type="url" name="website_url" value="{{ old('website_url', $settings->website_url) }}" @class(['form-control', 'is-invalid' => $errors->has('website_url')]) dir="ltr" maxlength="190">
            </x-field>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
        </form>

        <h3 class="sub-title mt-4">{{ __('settings.integration.last_sync') }}</h3>
        @if ($settings->last_sync_at)
            <p class="mb-0">
                <bdi dir="ltr">{{ fmt_datetime(\Illuminate\Support\Carbon::parse($settings->last_sync_at)) }}</bdi> ·
                @if ($settings->last_sync_status === 'ok')
                    <x-status-badge color="success">{{ __('settings.integration.sync_ok') }}</x-status-badge>
                    {{ __('settings.integration.sync_count', ['count' => $settings->last_sync_count]) }}
                @else
                    <x-status-badge color="danger">{{ $settings->last_sync_status }}</x-status-badge>
                @endif
            </p>
        @else
            <p class="text-muted mb-0">{{ __('settings.integration.last_sync_never') }}</p>
        @endif
    </x-panel>
@endsection
