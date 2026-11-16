@extends('layouts.app')

@section('title', $equipment->name)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('equipment.index') }}">{{ __('resources.equipment.equipment') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$equipment->name" :status="$equipment->ownership->label()">
        <x-slot:actions>
            @can('equipment.update')
                <a href="{{ route('equipment.edit', $equipment) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('resources.equipment.edit') }}</a>
            @endcan
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('resources.equipment.daily_cost') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($equipment->daily_cost) }}</bdi></span></div>
            @if ($equipment->type)
                <div class="figure"><span class="figure-label">{{ __('resources.equipment.type') }}</span><span class="figure-value">{{ $equipment->type->name }}</span></div>
            @endif
        </x-slot:figures>
    </x-record-header>

    @can('equipment.update')
        <x-panel :title="__('resources.equipment.assign')" class="panel-flush">
            @include('equipment.partials.assign-form', ['action' => route('equipment.assignments.store', $equipment), 'projects' => $projects])
        </x-panel>
    @endcan

    <x-panel :title="__('resources.equipment.assignments')" class="panel-flush">
        <livewire:tables.equipment-assignments-table :equipment-id="$equipment->id" />
    </x-panel>
@endsection
