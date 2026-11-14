@extends('layouts.app')

@section('title', __('quotations.quotations'))

@section('content')
    <x-page-header :title="__('quotations.quotations')">
        @can('quotations.create')
            <x-slot:actions>
                <a href="{{ route('quotations.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('quotations.new_quotation') }}</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <livewire:tables.quotations-table />
@endsection
