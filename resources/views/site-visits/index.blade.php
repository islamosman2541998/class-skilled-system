@extends('layouts.app')

@section('title', __('visits.visits'))

@section('content')
    <x-page-header :title="__('visits.visits')">
        <x-slot:actions>
            <a href="{{ route('my-visits') }}" class="btn btn-outline"><x-icon name="calendar" :size="16" /> {{ __('visits.my_visits') }}</a>
            @can('site_visits.create')
                <a href="{{ route('site-visits.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('visits.new_visit') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <livewire:tables.site-visits-table />
@endsection
