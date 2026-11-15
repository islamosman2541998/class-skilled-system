@extends('layouts.app')

@section('title', __('site.reports.reports'))

@section('content')
    <x-page-header :title="__('site.reports.reports')">
        <x-slot:actions>
            @can('daily_reports.create')
                <a href="{{ route('daily-reports.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('site.reports.new') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <livewire:tables.daily-reports-table />
@endsection
