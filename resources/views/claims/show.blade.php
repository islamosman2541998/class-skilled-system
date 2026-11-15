@extends('layouts.app')

@php
    $title = $claim->number ?? __('site.claims.draft_title');
    $qty = fn ($value) => rtrim(rtrim((string) $value, '0'), '.') ?: '0';
    $submitted = $claim->status === \App\Enums\ClaimStatus::Submitted;
@endphp

@section('title', $title)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('claims.index') }}">{{ __('site.claims.claims') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ route('projects.show', [$claim->project, 'tab' => 'claims']) }}"><bdi dir="ltr">{{ $claim->project->number }}</bdi></a></li>
        </ol>
    </nav>

    <x-record-header :title="$title" :status="$claim->status->label()" :status-color="$claim->status->color()">
        <x-slot:actions>
            @foreach (\App\Http\Controllers\ClaimController::$actionViews as $view)
                @include($view, ['claim' => $claim])
            @endforeach
            @if ($claim->isDraft())
                @can('claims.update')
                    <form method="POST" action="{{ route('claims.submit', $claim) }}" data-confirm="{{ __('site.claims.submit_confirm') }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-primary"><x-icon name="check" :size="16" /> {{ __('site.claims.submit') }}</button>
                    </form>
                    <a href="{{ route('claims.edit', $claim) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('site.claims.edit') }}</a>
                @endcan
                @can('claims.delete')
                    <form method="POST" action="{{ route('claims.destroy', $claim) }}" data-confirm="{{ __('table.confirm_delete') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-outline text-danger"><x-icon name="trash" :size="16" /> {{ __('table.delete') }}</button>
                    </form>
                @endcan
            @elseif ($submitted)
                @can('claims.approve')
                    <button type="button" class="btn btn-outline" data-bs-toggle="modal" data-bs-target="#claim-return">{{ __('site.claims.return') }}</button>
                @endcan
            @endif
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('site.claims.net') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($claim->net_amount) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('site.claims.total') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($claim->total) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('site.claims.period') }}</span><span class="figure-value"><bdi dir="ltr">{{ fmt_date($claim->period_from) }} - {{ fmt_date($claim->period_to) }}</bdi></span></div>
        </x-slot:figures>
    </x-record-header>

    @if ($claim->isDraft() && $claim->return_note)
        <div class="alert alert-warning"><strong>{{ __('site.claims.returned_note') }}:</strong> {{ $claim->return_note }}</div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            <x-panel :title="__('site.claims.lines')" class="panel-flush">
                <div class="table-responsive">
                    <table class="table q-view mb-0">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('site.claims.description') }}</th>
                                <th scope="col" class="num">{{ __('site.claims.contract_quantity') }}</th>
                                <th scope="col" class="num">{{ __('site.claims.unit_price') }}</th>
                                <th scope="col" class="num">{{ __('site.claims.previous') }}</th>
                                <th scope="col" class="num">{{ __('site.claims.current') }}</th>
                                <th scope="col" class="num">{{ __('site.claims.cumulative') }}</th>
                                <th scope="col" class="num">{{ __('site.claims.current_amount') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($claim->lines as $line)
                                <tr>
                                    <td>{{ $line->description() }}@if ($line->unit)<span class="text-muted small"> · {{ $line->unit->name }}</span>@endif</td>
                                    <td class="num"><bdi dir="ltr">{{ $qty($line->contract_quantity) }}</bdi></td>
                                    <td class="num"><bdi dir="ltr">{{ money($line->unit_price, false) }}</bdi></td>
                                    <td class="num"><bdi dir="ltr">{{ $qty($line->previous_quantity) }}</bdi></td>
                                    <td class="num"><bdi dir="ltr">{{ $qty($line->current_quantity) }}</bdi></td>
                                    <td class="num"><bdi dir="ltr">{{ $qty($line->cumulative_quantity) }} · {{ $qty($line->cumulativePercent()) }}%</bdi></td>
                                    <td class="num"><bdi dir="ltr">{{ money($line->current_amount, false) }}</bdi></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-panel>

            @if ($claim->notes)
                <x-panel :title="__('site.claims.notes')">
                    <p class="text-pre mb-0">{{ $claim->notes }}</p>
                </x-panel>
            @endif
        </div>
        <div class="col-xl-4">
            @include('claims.partials.totals', ['claim' => $claim])
            <x-panel :title="__('site.claims.claim')">
                <dl class="detail-list">
                    <div><dt>{{ __('site.claims.client') }}</dt><dd><a href="{{ route('clients.show', $claim->client) }}">{{ $claim->client->name }}</a></dd></div>
                    <div><dt>{{ __('site.claims.project') }}</dt><dd><a href="{{ route('projects.show', [$claim->project, 'tab' => 'claims']) }}"><bdi dir="ltr">{{ $claim->project->number }}</bdi></a> · {{ $claim->project->title }}</dd></div>
                    @if ($claim->creator)
                        <div><dt>{{ __('site.claims.created_by') }}</dt><dd>{{ $claim->creator->name }}</dd></div>
                    @endif
                    @if ($claim->submitted_at)
                        <div><dt>{{ __('site.claims.submitted_by') }}</dt><dd>{{ $claim->submitter?->name }} · <bdi dir="ltr">{{ fmt_datetime($claim->submitted_at) }}</bdi></dd></div>
                    @endif
                    @if ($claim->approved_at)
                        <div><dt>{{ __('site.claims.approved_by') }}</dt><dd>{{ $claim->approver?->name }} · <bdi dir="ltr">{{ fmt_datetime($claim->approved_at) }}</bdi></dd></div>
                    @endif
                </dl>
            </x-panel>
            <x-activity-tab :subject="$claim" />
        </div>
    </div>

    @if ($submitted)
        @can('claims.approve')
            @push('modals')
                <div class="modal fade" id="claim-return" tabindex="-1" aria-labelledby="claim-return-title" aria-hidden="true">
                    <div class="modal-dialog">
                        <form class="modal-content" method="POST" action="{{ route('claims.return', $claim) }}">
                            @csrf
                            @method('PATCH')
                            <div class="modal-header">
                                <h2 class="modal-title" id="claim-return-title">{{ __('site.claims.return') }}</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                            </div>
                            <div class="modal-body">
                                <x-field name="return_note" :label="__('site.claims.return_note')" for="claim-return-note" required>
                                    <textarea id="claim-return-note" name="return_note" class="form-control" rows="3" maxlength="2000" required></textarea>
                                </x-field>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                                <button type="submit" class="btn btn-primary">{{ __('site.claims.return') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endpush
        @endcan
    @endif
@endsection
