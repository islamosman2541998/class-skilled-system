@extends('layouts.app')

@section('title', __('resources.vouchers.vouchers'))

@section('content')
    <x-page-header :title="__('resources.vouchers.vouchers')">
        @can('payment_vouchers.create')
            <x-slot:actions>
                <a href="{{ route('payment-vouchers.create') }}" class="btn btn-primary"><x-icon name="plus" :size="16" /> {{ __('resources.vouchers.new') }}</a>
            </x-slot:actions>
        @endcan
    </x-page-header>

    <livewire:tables.payment-vouchers-table />
@endsection
