@extends('layouts.app')

@section('title', __('finance.invoices.invoices'))

@section('content')
    <x-page-header :title="__('finance.invoices.invoices')">
        <x-slot:actions>
            @can('invoices.create')
                <a href="{{ route('invoices.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('finance.invoices.new') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <livewire:tables.invoices-table />
@endsection
