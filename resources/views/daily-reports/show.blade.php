@extends('layouts.app')

@section('title', __('site.reports.report').' '.fmt_date($report->report_date))

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('daily-reports.index') }}">{{ __('site.reports.reports') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="__('site.reports.report').' '.fmt_date($report->report_date)" :status="$report->has_obstacles ? __('site.reports.has_obstacles') : null">
        <x-slot:actions>
            @can('daily_reports.update')
                <a href="{{ route('daily-reports.edit', $report) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('site.reports.edit') }}</a>
            @endcan
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('site.reports.project') }}</span><span class="figure-value"><a href="{{ route('projects.show', [$report->project, 'tab' => 'daily_reports']) }}"><bdi dir="ltr">{{ $report->project->number }}</bdi></a></span></div>
            <div class="figure"><span class="figure-label">{{ __('site.reports.engineer') }}</span><span class="figure-value">{{ $report->engineer?->name }}</span></div>
            <div class="figure"><span class="figure-label">{{ __('site.reports.labour_count') }}</span><span class="figure-value"><bdi dir="ltr">{{ $report->labour_count }}</bdi></span></div>
        </x-slot:figures>
    </x-record-header>

    <div class="row g-3">
        <div class="col-xl-7">
            <x-panel :title="__('site.reports.work_done')">
                <p class="text-pre mb-0">{{ $report->work_done }}</p>
            </x-panel>

            @if ($report->stages->isNotEmpty())
                <x-panel :title="__('site.reports.stages')" class="panel-flush">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('site.reports.stage') }}</th>
                                    <th scope="col" class="num">{{ __('site.reports.progress_set') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report->stages as $line)
                                    <tr>
                                        <td>{{ $line->stage->name }}</td>
                                        <td class="num"><bdi dir="ltr">{{ rtrim(rtrim((string) $line->progress, '0'), '.') ?: '0' }}%</bdi></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-panel>
            @endif

            @if ($report->obstacles)
                <x-panel :title="__('site.reports.obstacles')">
                    <p class="text-pre mb-0">{{ $report->obstacles }}</p>
                </x-panel>
            @endif

            @if ($report->notes)
                <x-panel :title="__('site.reports.notes')">
                    <p class="text-pre mb-0">{{ $report->notes }}</p>
                </x-panel>
            @endif

            <x-activity-tab :subject="$report" />
        </div>
        <div class="col-xl-5">
            <x-attachments :model="$report" :action="route('daily-reports.attachments.store', $report)" />
        </div>
    </div>
@endsection
