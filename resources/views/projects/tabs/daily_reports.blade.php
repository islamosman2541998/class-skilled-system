{{-- Daily reports of the project (spec §8.6, §8.13). --}}
@php
    $missingDays = \App\Services\Site\ReportCalendar::missingDays($project, today()->subDays(30), today());
    $canCreate = auth()->user()->can('daily_reports.create');
@endphp

@if ($project->status === \App\Enums\ProjectStatus::InProgress)
    <x-panel :title="__('site.reports.missing_days')">
        <p class="text-muted small mb-2">{{ __('site.reports.missing_hint') }}</p>
        @if ($missingDays === [])
            <p class="mb-0">{{ __('site.reports.none_missing') }}</p>
        @else
            <div class="d-flex flex-wrap gap-2">
                @foreach ($missingDays as $day)
                    @if ($canCreate)
                        <a href="{{ route('daily-reports.create', ['project' => $project->id, 'date' => $day]) }}" class="btn btn-outline btn-sm"><bdi dir="ltr">{{ fmt_date(\Illuminate\Support\Carbon::parse($day)) }}</bdi></a>
                    @else
                        <span class="btn btn-outline btn-sm disabled"><bdi dir="ltr">{{ fmt_date(\Illuminate\Support\Carbon::parse($day)) }}</bdi></span>
                    @endif
                @endforeach
            </div>
        @endif
    </x-panel>
@endif

<x-panel :title="__('site.reports.reports')" class="panel-flush">
    @can('daily_reports.create')
        <x-slot:tools>
            <a href="{{ route('daily-reports.create', ['project' => $project->id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('site.reports.new') }}</a>
        </x-slot:tools>
    @endcan
    <livewire:tables.daily-reports-table :project-id="$project->id" />
</x-panel>
