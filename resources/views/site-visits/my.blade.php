{{-- Mobile-first list of the engineer's visits (spec §4.4, §8.3). --}}
@extends('layouts.app')

@section('title', __('visits.my_visits'))

@section('content')
    <x-page-header :title="__('visits.my_visits')" />

    @foreach (['today' => $today, 'upcoming' => $upcoming] as $group => $visits)
        <h2 class="section-title">{{ __('visits.'.$group) }}</h2>
        @if ($visits->isEmpty())
            <x-panel><x-empty-state :message="__('visits.none_'.$group)" icon="calendar" /></x-panel>
        @else
            <div class="visit-cards">
                @foreach ($visits as $visit)
                    <article class="visit-card">
                        <div class="visit-card-time"><bdi dir="ltr">{{ $group === 'today' ? $visit->scheduled_at->format('H:i') : fmt_datetime($visit->scheduled_at) }}</bdi></div>
                        <h3 class="visit-card-title">{{ $visit->client->name }}</h3>
                        @if ($visit->lead?->service)
                            <div class="visit-card-meta">{{ $visit->lead->service->name }}</div>
                        @endif
                        @if ($visit->address)
                            <div class="visit-card-meta">{{ $visit->address }}</div>
                        @endif
                        <div class="visit-card-actions">
                            <a href="tel:{{ preg_replace('/[^0-9+]/', '', $visit->client->phone) }}" class="btn btn-outline"><x-icon name="phone" :size="18" /> {{ __('visits.call') }}</a>
                            @if ($visit->map_url)
                                <a href="{{ $visit->map_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline"><x-icon name="map-pin" :size="18" /> {{ __('visits.open_map') }}</a>
                            @endif
                            <a href="{{ route('site-visits.result', $visit) }}" class="btn btn-primary">{{ __('visits.record_result') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @endforeach
@endsection
