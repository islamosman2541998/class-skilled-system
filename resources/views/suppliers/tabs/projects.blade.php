{{-- Projects the supplier worked on through purchases or subcontracts (spec §8.15). --}}
@php
    $user = auth()->user();
    $projects = \App\Models\Project::query()->visibleTo($user)
        ->where(fn ($query) => $query->whereIn('id', $supplier->purchases()->select('project_id'))->orWhereIn('id', $supplier->subcontracts()->select('project_id')))
        ->withSum(['purchases as supplier_total' => fn ($query) => $query->where('supplier_id', $supplier->id)], 'total')
        ->orderByDesc('id')->get();
@endphp
<x-panel :title="__('resources.suppliers.tabs.projects')" class="panel-flush">
    @if ($projects->isEmpty())
        <x-empty-state :message="__('resources.suppliers.no_projects')" icon="building" />
    @else
        <div class="table-responsive">
            <table class="table q-view mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('resources.purchases.project') }}</th>
                        <th scope="col">{{ __('projects.status') }}</th>
                        <th scope="col" class="num">{{ __('resources.suppliers.figures.purchases_total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($projects as $project)
                        <tr>
                            <td><a href="{{ route('projects.show', [$project, 'tab' => 'purchases']) }}"><bdi dir="ltr">{{ $project->number }}</bdi></a> · {{ $project->title }}</td>
                            <td><x-status-badge :color="$project->status->color()">{{ $project->status->label() }}</x-status-badge></td>
                            <td class="num"><bdi dir="ltr">{{ money((string) ($project->getAttribute('supplier_total') ?? '0')) }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-panel>
