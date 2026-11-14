{{-- Accepted quotation → project (spec §8.5); once converted it links to the project. --}}
@php
    $project = $quotation->project;
@endphp
@if ($project)
    <a href="{{ route('projects.show', $project) }}" class="btn btn-outline"><x-icon name="building-skyscraper" :size="16" /> <bdi dir="ltr">{{ $project->number }}</bdi></a>
@elseif ($quotation->status === \App\Enums\QuotationStatus::Accepted)
    @can('projects.create')
        <a href="{{ route('quotations.convert.create', $quotation) }}" class="btn btn-primary"><x-icon name="building-skyscraper" :size="16" /> {{ __('projects.convert') }}</a>
    @endcan
@endif
