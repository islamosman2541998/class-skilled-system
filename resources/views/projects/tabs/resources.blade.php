{{-- Labour and equipment of the project (spec §8.6, §8.17). --}}
@php($labour = $project->attendances()->sum('cost'))
<x-panel :title="__('resources.attendance.attendance')" class="panel-flush">
    <x-slot:tools>
        <span class="text-muted small">{{ __('resources.attendance.labour_cost') }}: <bdi dir="ltr">{{ money((string) $labour) }}</bdi></span>
        @if (auth()->user()->can('attendance.create') || auth()->user()->can('attendance.update'))
            <a href="{{ route('attendance.sheet', ['project' => $project->id]) }}" class="btn btn-outline btn-sm"><x-icon name="calendar-check" :size="16" /> {{ __('resources.attendance.sheet') }}</a>
        @endif
    </x-slot:tools>
    <livewire:tables.attendance-table :project-id="$project->id" />
</x-panel>

@foreach (\App\Http\Controllers\AttendanceController::$projectViews as $view)
    @include($view, ['project' => $project])
@endforeach
