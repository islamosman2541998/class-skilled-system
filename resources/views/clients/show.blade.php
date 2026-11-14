@extends('layouts.app')

@section('title', $client->name)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('clients.index') }}">{{ __('clients.clients') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$client->name" :status="$client->type->label()" :subtitle="$client->name_en && app()->getLocale() === 'ar' ? $client->name_en : null">
        <x-slot:actions>
            @foreach (\App\Http\Controllers\ClientController::$actionViews as $view)
                @include($view, ['client' => $client])
            @endforeach
            @can('clients.update')
                <a href="{{ route('clients.edit', $client) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('clients.edit_client') }}</a>
            @endcan
        </x-slot:actions>
        <x-slot:figures>
            @foreach ($figures as $key => $value)
                <div class="figure">
                    <span class="figure-label">{{ __('clients.figures.'.$key) }}</span>
                    <span class="figure-value"><bdi dir="ltr">{{ $value }}</bdi></span>
                </div>
            @endforeach
        </x-slot:figures>
    </x-record-header>

    <x-tabs :tabs="$tabs" :active="$active" />

    @switch($active)
        @case('contacts')
            @include('clients.partials.contacts-tab')
            @break
        @case('attachments')
            <x-attachments :model="$client" :action="route('clients.attachments.store', $client)" />
            @break
        @case('activity')
            <x-activity-tab :subject="$client" />
            @break
        @case('overview')
            @include('clients.partials.overview')
            @break
        @default
            @includeIf('clients.tabs.'.$active, ['client' => $client])
    @endswitch
@endsection
