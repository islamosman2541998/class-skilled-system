@extends('layouts.app')

@section('title', __('users.users'))

@section('content')
    <x-page-header :title="__('users.users')">
        @can('users.create')
            <x-slot:actions>
                <a href="{{ route('users.create') }}" class="btn btn-primary">
                    <x-icon name="plus" :size="16" />
                    {{ __('users.new_user') }}
                </a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <livewire:tables.users-table />
@endsection
