@extends('layouts.app')

@section('title', __('maintenance.maintenance'))

@section('content')
    <x-page-header :title="__('maintenance.maintenance')">
        <x-slot:actions>
            @can('maintenance.create')
                <a href="{{ route('maintenance.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('maintenance.new_request') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <livewire:tables.maintenance-table />
@endsection
