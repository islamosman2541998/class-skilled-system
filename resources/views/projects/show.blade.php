@extends('layouts.app')

@php
    $days = $project->daysLeft();
@endphp

@section('title', $project->number)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('projects.index') }}">{{ __('projects.projects') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$project->title" :status="$project->status->label()" :status-color="$project->status->color()" :subtitle="$project->client->name">
        <x-slot:actions>
            @foreach (\App\Http\Controllers\ProjectController::$actionViews as $view)
                @include($view, ['project' => $project])
            @endforeach
            @can('projects.update')
                <a href="{{ route('projects.edit', $project) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('projects.edit_project') }}</a>
            @endcan
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure">
                <span class="figure-label">{{ __('projects.progress') }}</span>
                <span class="figure-value"><bdi dir="ltr">{{ rtrim(rtrim($project->progress, '0'), '.') ?: '0' }}%</bdi></span>
                <div class="progress-line"><span style="inline-size: {{ min(100, (float) $project->progress) }}%"></span></div>
            </div>
            <div class="figure">
                <span class="figure-label">{{ __('projects.planned_end_date') }}</span>
                <span @class(['figure-value', 'text-danger' => $project->isLate()])>
                    @if ($days === null)
                        {{ __('projects.not_yet') }}
                    @elseif ($days < 0 && ! $project->status->isClosed())
                        {{ __('projects.days_late', ['days' => abs($days)]) }}
                    @elseif (! $project->status->isClosed())
                        {{ __('projects.days_left', ['days' => $days]) }}
                    @else
                        <bdi dir="ltr">{{ fmt_date($project->planned_end_date) }}</bdi>
                    @endif
                </span>
            </div>
            <div class="figure"><span class="figure-label">{{ __('projects.contract_value') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($project->contract_value) }}</bdi></span></div>
            @foreach (['invoiced' => $project->invoiced_total, 'collected' => $project->collected_total, 'outstanding' => $project->outstanding()] as $label => $amount)
                <div class="figure"><span class="figure-label">{{ __('projects.'.$label) }}</span><span class="figure-value"><bdi dir="ltr">{{ money($amount) }}</bdi></span></div>
            @endforeach
            @can('finance.costs')
                <div class="figure"><span class="figure-label">{{ __('projects.cost') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($project->cost_total) }}</bdi></span></div>
            @endcan
            @can('finance.profitability')
                <div class="figure">
                    <span class="figure-label">{{ __('projects.profit') }}</span>
                    <span @class(['figure-value', 'text-danger' => bccomp($project->profit(), '0', 2) < 0])><bdi dir="ltr">{{ money($project->profit()) }}</bdi></span>
                </div>
                <div class="figure">
                    <span class="figure-label">{{ __('projects.margin') }}</span>
                    <span class="figure-value"><bdi dir="ltr">{{ $project->margin() !== null ? rtrim(rtrim($project->margin(), '0'), '.').'%' : '' }}</bdi></span>
                </div>
            @endcan
        </x-slot:figures>
    </x-record-header>

    <x-tabs :tabs="$tabs" :active="$active" />

    @switch($active)
        @case('attachments')
            <x-attachments :model="$project" :action="route('projects.attachments.store', $project)" />
            @break
        @case('activity')
            <x-activity-tab :subject="$project" />
            @break
        @case('overview')
            @include('projects.partials.overview')
            @break
        @default
            @include('projects.tabs.'.$active, ['project' => $project])
    @endswitch
@endsection
