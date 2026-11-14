@extends('layouts.app')

@section('title', __('clients.clients'))

@section('content')
    <x-page-header :title="__('clients.clients')">
        @can('clients.create')
            <x-slot:actions>
                <a href="{{ route('clients.create') }}" class="btn btn-primary">
                    <x-icon name="plus" :size="16" />
                    {{ __('clients.new_client') }}
                </a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <livewire:tables.clients-table />
@endsection
