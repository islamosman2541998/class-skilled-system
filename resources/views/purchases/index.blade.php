@extends('layouts.app')

@section('title', __('resources.purchases.purchases'))

@section('content')
    <x-page-header :title="__('resources.purchases.purchases')">
        @can('purchases.create')
            <x-slot:actions>
                <a href="{{ route('purchases.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('resources.purchases.new') }}</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <livewire:tables.purchases-table />
@endsection
