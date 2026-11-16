@extends('layouts.app')

@section('title', __('resources.workers.workers'))

@section('content')
    <x-page-header :title="__('resources.workers.workers')">
        @can('workers.create')
            <x-slot:actions>
                <a href="{{ route('workers.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('resources.workers.new') }}</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <livewire:tables.workers-table />
@endsection
