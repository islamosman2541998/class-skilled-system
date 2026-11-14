{{-- Variation orders of a project (spec §8.9). --}}
@php
    $variations = $project->variations()->get();
@endphp

<x-panel :title="__('projects.variations.title')" class="panel-flush">
    @can('projects.update')
        <x-slot:tools>
            <a href="{{ route('projects.variations.create', $project) }}" class="btn btn-primary btn-sm"><x-icon name="plus" :size="16" /> {{ __('projects.variations.new') }}</a>
        </x-slot:tools>
    @endcan

    @if ($variations->isEmpty())
        <x-empty-state :message="__('projects.variations.empty')" icon="file-diff" />
    @else
        <div class="table-responsive">
            <table class="table table-hover q-view mb-0">
                <thead>
                    <tr>
                        <th scope="col">{{ __('projects.variations.number') }}</th>
                        <th scope="col">{{ __('projects.variations.description') }}</th>
                        <th scope="col">{{ __('projects.status') }}</th>
                        <th scope="col" class="num">{{ __('projects.variations.total') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($variations as $variation)
                        <tr>
                            <td><a href="{{ route('projects.variations.show', [$project, $variation]) }}" class="fw-semibold"><bdi dir="ltr">{{ $variation->number }}</bdi></a></td>
                            <td>{{ \Illuminate\Support\Str::limit($variation->description, 90) }}</td>
                            <td><x-status-badge :color="$variation->status->color()">{{ $variation->status->label() }}</x-status-badge></td>
                            <td class="num"><bdi dir="ltr">{{ money($variation->total) }}</bdi></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-panel>
