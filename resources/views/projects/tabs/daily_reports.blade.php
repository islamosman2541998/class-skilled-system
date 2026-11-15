{{-- Daily reports of the project (spec §8.6, §8.13). --}}
<x-panel :title="__('site.reports.reports')" class="panel-flush">
    @can('daily_reports.create')
        <x-slot:tools>
            <a href="{{ route('daily-reports.create', ['project' => $project->id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('site.reports.new') }}</a>
        </x-slot:tools>
    @endcan
    <livewire:tables.daily-reports-table :project-id="$project->id" />
</x-panel>
