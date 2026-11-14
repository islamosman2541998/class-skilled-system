@extends('layouts.app')

@section('title', __('leads.leads'))

@section('content')
    <x-page-header :title="__('leads.leads')">
        <x-slot:actions>
            @foreach (\App\Http\Controllers\LeadController::$indexActionViews as $view)
                @include($view)
            @endforeach
            @can('leads.create')
                <a href="{{ route('leads.create') }}" class="btn btn-primary">
                    <x-icon name="plus" :size="16" />
                    {{ __('leads.new_lead') }}
                </a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <livewire:tables.leads-table />
@endsection
