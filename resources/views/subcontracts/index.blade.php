@extends('layouts.app')

@section('title', __('resources.subcontracts.subcontracts'))

@section('content')
    <x-page-header :title="__('resources.subcontracts.subcontracts')">
        @can('subcontracts.create')
            <x-slot:actions>
                <a href="{{ route('subcontracts.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('resources.subcontracts.new') }}</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <livewire:tables.subcontracts-table />
@endsection
