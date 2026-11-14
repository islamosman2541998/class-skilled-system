@extends('layouts.app')

@section('title', $visit->number)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('site-visits.index') }}">{{ __('visits.visits') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$visit->client->name" :status="$visit->status->label()" :status-color="$visit->status->color()">
        <x-slot:actions>
            @can('site_visits.update')
                @if ($visit->status === \App\Enums\SiteVisitStatus::Scheduled)
                    <a href="{{ route('site-visits.result', $visit) }}" class="btn btn-primary">{{ __('visits.record_result') }}</a>
                @endif
                <a href="{{ route('site-visits.edit', $visit) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('visits.edit_visit') }}</a>
            @endcan
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('visits.number') }}</span><span class="figure-value"><bdi dir="ltr">{{ $visit->number }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('visits.scheduled_at') }}</span><span class="figure-value"><bdi dir="ltr">{{ fmt_datetime($visit->scheduled_at) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('visits.engineer') }}</span><span class="figure-value">{{ $visit->engineer->name }}</span></div>
        </x-slot:figures>
    </x-record-header>

    <div class="row g-3">
        <div class="col-lg-7">
            <x-panel :title="__('visits.details')">
                <dl class="detail-list">
                    <div><dt>{{ __('visits.client') }}</dt><dd><a href="{{ route('clients.show', $visit->client) }}">{{ $visit->client->name }}</a> · <bdi dir="ltr">{{ $visit->client->phone }}</bdi></dd></div>
                    @if ($visit->lead)
                        <div><dt>{{ __('visits.lead') }}</dt><dd><a href="{{ route('leads.show', $visit->lead) }}"><bdi dir="ltr">{{ $visit->lead->number }}</bdi></a></dd></div>
                    @endif
                    @if ($visit->address)
                        <div><dt>{{ __('visits.address') }}</dt><dd>{{ $visit->address }}</dd></div>
                    @endif
                    @if ($visit->map_url)
                        <div><dt>{{ __('visits.map_url') }}</dt><dd><a href="{{ $visit->map_url }}" target="_blank" rel="noopener noreferrer">{{ __('visits.open_map') }}</a></dd></div>
                    @endif
                    @if ($visit->result_at)
                        <div><dt>{{ __('visits.result_at') }}</dt><dd><bdi dir="ltr">{{ fmt_datetime($visit->result_at) }}</bdi></dd></div>
                    @endif
                </dl>
                @if ($visit->notes)
                    <h3 class="sub-title mt-3">{{ __('visits.notes') }}</h3>
                    <p class="text-pre mb-0">{{ $visit->notes }}</p>
                @endif
                @if ($visit->measurements)
                    <h3 class="sub-title mt-3">{{ __('visits.measurements') }}</h3>
                    <p class="text-pre mb-0">{{ $visit->measurements }}</p>
                @endif
            </x-panel>
            <x-activity-tab :subject="$visit" />
        </div>
        <div class="col-lg-5">
            <x-attachments :model="$visit" :action="route('site-visits.attachments.store', $visit)" />
        </div>
    </div>
@endsection
