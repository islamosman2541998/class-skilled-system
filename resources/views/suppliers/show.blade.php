@extends('layouts.app')

@section('title', $supplier->name)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('suppliers.index') }}">{{ __('resources.suppliers.suppliers') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$supplier->name" :status="$supplier->type->label()" :status-color="$supplier->type->color()" :subtitle="$supplier->name_en && app()->getLocale() === 'ar' ? $supplier->name_en : null">
        <x-slot:actions>
            @foreach (\App\Http\Controllers\SupplierController::$actionViews as $view)
                @include($view, ['supplier' => $supplier])
            @endforeach
            @can('suppliers.update')
                <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('resources.suppliers.edit') }}</a>
            @endcan
        </x-slot:actions>
        @if ($figures !== [])
            <x-slot:figures>
                @foreach ($figures as $key => $value)
                    <div class="figure">
                        <span class="figure-label">{{ __('resources.suppliers.figures.'.$key) }}</span>
                        <span class="figure-value"><bdi dir="ltr">{{ $value }}</bdi></span>
                    </div>
                @endforeach
            </x-slot:figures>
        @endif
    </x-record-header>

    <x-tabs :tabs="$tabs" :active="$active" />

    @switch($active)
        @case('attachments')
            <x-attachments :model="$supplier" :action="route('suppliers.attachments.store', $supplier)" />
            @break
        @case('activity')
            <x-activity-tab :subject="$supplier" />
            @break
        @default
            @includeIf('suppliers.tabs.'.$active, ['supplier' => $supplier])
    @endswitch
@endsection
