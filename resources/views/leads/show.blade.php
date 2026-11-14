@extends('layouts.app')

@section('title', $lead->number)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('leads.index') }}">{{ __('leads.leads') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$lead->displayName()" :status="$lead->status->name" :status-color="$lead->status->color ?: 'neutral'">
        <x-slot:actions>
            @includeIf('leads.partials.actions')
            @can('leads.update')
                <a href="{{ route('leads.edit', $lead) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('leads.edit_lead') }}</a>
            @endcan
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('leads.number') }}</span><span class="figure-value"><bdi dir="ltr">{{ $lead->number }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('leads.service') }}</span><span class="figure-value">{{ $lead->service?->name }}</span></div>
            <div class="figure"><span class="figure-label">{{ __('leads.assigned_to') }}</span><span class="figure-value">{{ $lead->assignee?->name ?? __('leads.unassigned') }}</span></div>
            <div class="figure"><span class="figure-label">{{ __('leads.follow_up_at') }}</span><span class="figure-value"><bdi dir="ltr">{{ $lead->follow_up_at ? fmt_datetime($lead->follow_up_at) : '' }}</bdi></span></div>
        </x-slot:figures>
    </x-record-header>

    <div class="row g-3">
        <div class="col-lg-7">
            <x-panel :title="__('leads.details')">
                <dl class="detail-list">
                    @if ($lead->client)
                        <div><dt>{{ __('leads.client') }}</dt><dd><a href="{{ route('clients.show', $lead->client) }}">{{ $lead->client->name }}</a></dd></div>
                    @endif
                    @if ($lead->contact_name)
                        <div><dt>{{ __('leads.contact_name') }}</dt><dd>{{ $lead->contact_name }}</dd></div>
                    @endif
                    @if ($lead->contact_phone)
                        <div><dt>{{ __('leads.contact_phone') }}</dt><dd><bdi dir="ltr">{{ $lead->contact_phone }}</bdi></dd></div>
                    @endif
                    @if ($lead->contact_email)
                        <div><dt>{{ __('leads.contact_email') }}</dt><dd><bdi dir="ltr">{{ $lead->contact_email }}</bdi></dd></div>
                    @endif
                    @foreach (['facility_type', 'project_state', 'area_text', 'expected_start'] as $field)
                        @if ($lead->{$field})
                            <div><dt>{{ __('leads.'.$field) }}</dt><dd>{{ $lead->{$field} }}</dd></div>
                        @endif
                    @endforeach
                    @if ($lead->emirate)
                        <div><dt>{{ __('leads.emirate') }}</dt><dd>{{ $lead->emirate->name }}@if ($lead->area){{ __('app.comma') }}{{ $lead->area->name }}@endif</dd></div>
                    @endif
                    @if ($lead->area_m2)
                        <div><dt>{{ __('leads.area_m2') }}</dt><dd><bdi dir="ltr">{{ number_format((float) $lead->area_m2, 2) }}</bdi></dd></div>
                    @endif
                    <div><dt>{{ __('leads.source') }}</dt><dd>{{ $lead->source?->name }}</dd></div>
                    <div><dt>{{ __('leads.created_by') }}</dt><dd>{{ $lead->creator?->name }} · <bdi dir="ltr">{{ fmt_datetime($lead->created_at) }}</bdi></dd></div>
                    @if ($lead->rejectionReason)
                        <div><dt>{{ __('leads.rejection_reason') }}</dt><dd>{{ $lead->rejectionReason->name }}@if ($lead->rejection_note) — {{ $lead->rejection_note }}@endif</dd></div>
                    @endif
                </dl>
                @if ($lead->description)
                    <p class="mt-3 mb-0 text-pre">{{ $lead->description }}</p>
                @endif
            </x-panel>

            @foreach ($extraViews as $view)
                @include($view, ['lead' => $lead])
            @endforeach
        </div>

        <div class="col-lg-5">
            <x-attachments :model="$lead" :action="route('leads.attachments.store', $lead)" />
            <x-activity-tab :subject="$lead" />
        </div>
    </div>
@endsection
