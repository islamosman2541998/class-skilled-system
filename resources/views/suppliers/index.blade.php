@extends('layouts.app')

@section('title', __('resources.suppliers.suppliers'))

@section('content')
    <x-page-header :title="__('resources.suppliers.suppliers')">
        @can('suppliers.create')
            <x-slot:actions>
                <a href="{{ route('suppliers.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('resources.suppliers.new') }}</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <livewire:tables.suppliers-table />
@endsection
