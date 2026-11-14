@extends('layouts.app')

@section('title', __('finance.expenses.expenses'))

@section('content')
    <x-page-header :title="__('finance.expenses.expenses')">
        <x-slot:actions>
            @can('expenses.create')
                <a href="{{ route('expenses.quick') }}" class="btn btn-outline"><x-icon name="camera" :size="16" /> {{ __('finance.expenses.quick') }}</a>
                <a href="{{ route('expenses.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('finance.expenses.new') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <livewire:tables.expenses-table />
@endsection
