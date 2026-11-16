@extends('layouts.app')

@section('title', __('resources.attendance.attendance'))

@section('content')
    <x-page-header :title="__('resources.attendance.attendance')">
        @if (auth()->user()->can('attendance.create') || auth()->user()->can('attendance.update'))
            <x-slot:actions>
                <a href="{{ route('attendance.sheet') }}" class="btn btn-primary"><x-icon name="calendar-check" :size="16" /> {{ __('resources.attendance.sheet') }}</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <livewire:tables.attendance-table />
@endsection
