@extends('layouts.app')

@section('title', __('finance.receipts.receipts'))

@section('content')
    <x-page-header :title="__('finance.receipts.receipts')">
        <x-slot:actions>
            @can('receipts.create')
                <a href="{{ route('receipts.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('finance.receipts.new') }}</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <livewire:tables.receipts-table />
@endsection
