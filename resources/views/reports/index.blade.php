@extends('layouts.app')

@section('title', __('reports.reports'))

@section('content')
    <x-page-header :title="__('reports.reports')" />

    @forelse ($groups as $group => $reports)
        <x-panel :title="__('reports.groups.'.$group)">
            <div class="report-list">
                @foreach ($reports as $key => $report)
                    <a href="{{ route('reports.show', $key) }}" class="report-link">
                        <span class="fw-semibold">{{ __($report['label']) }}</span>
                        <span class="text-muted">{{ __($report['hint']) }}</span>
                    </a>
                @endforeach
            </div>
        </x-panel>
    @empty
        <x-empty-state :message="__('reports.none')" icon="chart-bar" />
    @endforelse
@endsection
