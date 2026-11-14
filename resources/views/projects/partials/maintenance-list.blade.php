{{-- Maintenance requests of the project on its handover tab (spec §8.18). --}}
@can('maintenance.view')
    @php
        $requests = $project->maintenanceRequests()->visibleTo(auth()->user())->with('assignee')->latest('request_date')->latest('id')->get();
    @endphp

    <x-panel :title="__('maintenance.maintenance')" class="mt-3">
        @can('maintenance.create')
            <x-slot:tools>
                <a href="{{ route('maintenance.create', ['project' => $project->id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('maintenance.new_request') }}</a>
            </x-slot:tools>
        @endcan

        @forelse ($requests as $row)
            <div @class(['d-flex flex-wrap align-items-center gap-2 py-2', 'border-top' => ! $loop->first])>
                @include('maintenance.partials.number', ['row' => $row])
                <span class="text-muted"><bdi dir="ltr">{{ fmt_date($row->request_date) }}</bdi></span>
                <span class="flex-grow-1 text-truncate">{{ \Illuminate\Support\Str::limit($row->description, 60) }}</span>
                @include('maintenance.partials.warranty', ['row' => $row])
                @include('maintenance.partials.status', ['row' => $row])
            </div>
        @empty
            <x-empty-state :message="__('maintenance.none')" icon="tool" />
        @endforelse
    </x-panel>
@endcan
