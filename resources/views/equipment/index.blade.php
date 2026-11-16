@extends('layouts.app')

@section('title', __('resources.equipment.equipment'))

@section('content')
    <x-page-header :title="__('resources.equipment.equipment')">
        @can('equipment.create')
            <x-slot:actions>
                <a href="{{ route('equipment.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('resources.equipment.new') }}</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <livewire:tables.equipment-table />
@endsection
