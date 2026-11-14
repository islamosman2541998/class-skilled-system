@extends('layouts.app')

@section('title', __('projects.projects'))

@section('content')
    <x-page-header :title="__('projects.projects')">
        @can('projects.create')
            <x-slot:actions>
                <a href="{{ route('projects.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('projects.new_project') }}</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <livewire:tables.projects-table />
@endsection
