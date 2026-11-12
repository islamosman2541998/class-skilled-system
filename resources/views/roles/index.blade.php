@extends('layouts.app')

@section('title', __('users.roles'))

@section('content')
    <x-page-header :title="__('users.roles')">
        @can('roles.create')
            <x-slot:actions>
                <a href="{{ route('roles.create') }}" class="btn btn-primary">
                    <x-icon name="plus" :size="16" />
                    {{ __('users.new_role') }}
                </a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <livewire:tables.roles-table />
@endsection
