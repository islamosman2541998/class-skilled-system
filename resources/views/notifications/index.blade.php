@extends('layouts.app')

@section('title', __('notifications.notifications'))

@section('content')
    <x-page-header :title="__('notifications.notifications')">
        <x-slot:actions>
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-outline">{{ __('notifications.mark_all_read') }}</button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <livewire:tables.notifications-table />
@endsection
