@extends('layouts.app')

@php
    $project = $maintenance->project;
    $handover = $project->handover;
@endphp

@section('title', $maintenance->number)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('maintenance.index') }}">{{ __('maintenance.maintenance') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$project->title" :status="$maintenance->status->label()" :status-color="$maintenance->status->color()">
        <x-slot:actions>
            @can('maintenance.update')
                <a href="{{ route('maintenance.edit', $maintenance) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('maintenance.edit_request') }}</a>
            @endcan
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('maintenance.number') }}</span><span class="figure-value"><bdi dir="ltr">{{ $maintenance->number }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('maintenance.request_date') }}</span><span class="figure-value"><bdi dir="ltr">{{ fmt_date($maintenance->request_date) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('maintenance.cost') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($maintenance->cost) }}</bdi></span></div>
        </x-slot:figures>
    </x-record-header>

    <div class="row g-3">
        <div class="col-lg-7">
            <x-panel :title="__('maintenance.details')">
                <dl class="detail-list">
                    <div><dt>{{ __('maintenance.project') }}</dt><dd><a href="{{ route('projects.show', [$project, 'tab' => 'handover']) }}"><bdi dir="ltr">{{ $project->number }}</bdi></a> · {{ $project->title }}</dd></div>
                    <div><dt>{{ __('maintenance.client') }}</dt><dd>{{ $project->client->name }}</dd></div>
                    <div>
                        <dt>{{ __('maintenance.warranty') }}</dt>
                        <dd>
                            @include('maintenance.partials.warranty', ['row' => $maintenance])
                            <span class="text-muted ms-1">
                                @if ($handover)
                                    {{ __('maintenance.warranty_end') }}: <bdi dir="ltr">{{ fmt_date($handover->warranty_end) }}</bdi>
                                @else
                                    {{ __('maintenance.no_handover') }}
                                @endif
                            </span>
                        </dd>
                    </div>
                    <div><dt>{{ __('maintenance.assigned_to') }}</dt><dd>{{ $maintenance->assignee->name ?? __('maintenance.unassigned') }}</dd></div>
                </dl>
                <h3 class="sub-title mt-3">{{ __('maintenance.description') }}</h3>
                <p class="text-pre mb-0">{{ $maintenance->description }}</p>
                @if ($maintenance->notes)
                    <h3 class="sub-title mt-3">{{ __('maintenance.notes') }}</h3>
                    <p class="text-pre mb-0">{{ $maintenance->notes }}</p>
                @endif
            </x-panel>
            <x-activity-tab :subject="$maintenance" />
        </div>
        <div class="col-lg-5">
            <x-attachments :model="$maintenance" :action="route('maintenance.attachments.store', $maintenance)" />
        </div>
    </div>
@endsection
