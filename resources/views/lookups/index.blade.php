@extends('layouts.app')

@section('title', $type->label())

@section('content')
    <x-page-header :title="$type->label()" :breadcrumbs="[__('settings.lists_title') => null]">
        <x-slot:actions>
            @if ($orderable->isNotEmpty())
                @can('lookups.update')
                    <button type="button" class="btn btn-outline" data-bs-toggle="modal" data-bs-target="#lookup-order-modal">
                        <x-icon name="arrows-sort" :size="16" />
                        {{ __('settings.reorder') }}
                    </button>
                @endcan
            @endif
            @can('lookups.create')
                <button type="button" class="btn btn-primary" data-lookup-new>
                    <x-icon name="plus" :size="16" />
                    {{ __('app.add') }}
                </button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <nav class="list-switch" aria-label="{{ __('settings.lists_title') }}">
        @foreach ($types as $item)
            <a href="{{ route('lookups.index', $item->value) }}" @class(['list-switch-item', 'is-active' => $item === $type]) @if ($item === $type) aria-current="page" @endif>{{ $item->label() }}</a>
        @endforeach
    </nav>

    <livewire:tables.lookups-table :type="$type->value" :key="'lookups-'.$type->value" />
@endsection

@push('modals')
    @canany(['lookups.create', 'lookups.update'])
        @include('lookups.form-modal')
    @endcanany

    @if ($orderable->isNotEmpty())
        @can('lookups.update')
            <div class="modal fade" id="lookup-order-modal" tabindex="-1" aria-labelledby="lookup-order-title" aria-hidden="true">
                <div class="modal-dialog modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h2 class="modal-title" id="lookup-order-title">{{ __('settings.reorder') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                        </div>
                        <div class="modal-body">
                            <ul class="sort-list" data-sortable data-url="{{ route('lookups.reorder', $type->value) }}">
                                @foreach ($orderable as $item)
                                    <li class="sort-item" data-id="{{ $item->id }}">
                                        <span class="sort-handle" aria-hidden="true"><x-icon name="grip-vertical" :size="16" /></span>
                                        <span @class(['text-muted' => ! $item->is_active])>{{ $item->name }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                            <button type="button" class="btn btn-primary" data-sortable-save>{{ __('app.save') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        @endcan
    @endif
@endpush
