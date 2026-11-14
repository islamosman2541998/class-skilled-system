@extends('layouts.app')

@php
    $status = $quotation->displayStatus();
    $sections = $quotation->items->groupBy(fn ($item) => (string) $item->section(app()->getLocale()));
@endphp

@section('title', $quotation->number)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('quotations.index') }}">{{ __('quotations.quotations') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$quotation->title" :status="$status->label()" :status-color="$status->color()" :subtitle="$quotation->client->name">
        <x-slot:actions>
            @foreach ($actionViews as $view)
                @include($view, ['quotation' => $quotation])
            @endforeach
            @can('quotations.update')
                @unless ($quotation->isLocked())
                    <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('quotations.edit_quotation') }}</a>
                @endunless
            @endcan
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('quotations.number') }}</span><span class="figure-value"><bdi dir="ltr">{{ $quotation->number }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('quotations.issue_date') }}</span><span class="figure-value"><bdi dir="ltr">{{ fmt_date($quotation->issue_date) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('quotations.valid_until') }}</span><span class="figure-value"><bdi dir="ltr">{{ fmt_date($quotation->valid_until) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('quotations.assigned_to') }}</span><span class="figure-value">{{ $quotation->assignee->name }}</span></div>
            <div class="figure"><span class="figure-label">{{ __('quotations.total') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($quotation->total) }}</bdi></span></div>
        </x-slot:figures>
    </x-record-header>

    @if ($quotation->superseded_at)
        <div class="alert alert-info">{{ __('quotations.locked') }}</div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            <x-panel :title="__('quotations.items')" class="panel-flush">
                <div class="table-responsive">
                    <table class="table q-view">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('quotations.description') }}</th>
                                <th scope="col">{{ __('quotations.unit') }}</th>
                                <th scope="col" class="num">{{ __('quotations.quantity') }}</th>
                                <th scope="col" class="num">{{ __('quotations.unit_price') }}</th>
                                <th scope="col" class="num">{{ __('quotations.line_total') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sections as $section => $sectionItems)
                                @if ($section !== '')
                                    <tr class="q-section-row"><th colspan="5" scope="rowgroup">{{ $section }}</th></tr>
                                @endif
                                @foreach ($sectionItems as $item)
                                    <tr>
                                        <td class="text-pre">{{ $item->description(app()->getLocale()) }}</td>
                                        <td>{{ $item->unit?->name }}</td>
                                        <td class="num"><bdi dir="ltr">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</bdi></td>
                                        <td class="num"><bdi dir="ltr">{{ money($item->unit_price, false) }}</bdi></td>
                                        <td class="num"><bdi dir="ltr">{{ money($item->line_total, false) }}</bdi></td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr><th colspan="4" class="text-end">{{ __('quotations.subtotal') }}</th><td class="num"><bdi dir="ltr">{{ money($quotation->subtotal) }}</bdi></td></tr>
                            @if ((float) $quotation->discount_amount > 0)
                                <tr><th colspan="4" class="text-end">{{ __('quotations.discount') }}@if ($quotation->discount_type === \App\Enums\DiscountType::Percent) <bdi dir="ltr">({{ rtrim(rtrim($quotation->discount_value, '0'), '.') }}%)</bdi>@endif</th><td class="num"><bdi dir="ltr">{{ money($quotation->discount_amount) }}</bdi></td></tr>
                                <tr><th colspan="4" class="text-end">{{ __('quotations.taxable') }}</th><td class="num"><bdi dir="ltr">{{ money($quotation->taxable_amount) }}</bdi></td></tr>
                            @endif
                            <tr><th colspan="4" class="text-end">{{ __('quotations.vat', ['rate' => rtrim(rtrim($quotation->vat_rate, '0'), '.')]) }}</th><td class="num"><bdi dir="ltr">{{ money($quotation->vat_amount) }}</bdi></td></tr>
                            <tr class="q-grand"><th colspan="4" class="text-end">{{ __('quotations.total') }}</th><td class="num"><bdi dir="ltr">{{ money($quotation->total) }}</bdi></td></tr>
                        </tfoot>
                    </table>
                </div>
            </x-panel>

            <x-panel :title="__('quotations.payments')">
                <ul class="contact-list">
                    @foreach ($quotation->payments as $payment)
                        <li>
                            <span>{{ $payment->label(app()->getLocale()) }} <span class="text-muted"><bdi dir="ltr">{{ rtrim(rtrim($payment->percent, '0'), '.') }}%</bdi></span></span>
                            <bdi dir="ltr" class="fw-semibold">{{ money($payment->amount) }}</bdi>
                        </li>
                    @endforeach
                </ul>
            </x-panel>

            @php($terms = app()->getLocale() === 'en' && $quotation->terms_en ? $quotation->terms_en : $quotation->terms_ar)
            @if ($terms)
                <x-panel :title="__('quotations.terms')"><p class="text-pre mb-0">{{ $terms }}</p></x-panel>
            @endif
        </div>

        <div class="col-xl-4">
            <x-panel :title="__('quotations.header')">
                <dl class="detail-list detail-list-1">
                    <div><dt>{{ __('quotations.client') }}</dt><dd><a href="{{ route('clients.show', $quotation->client) }}">{{ $quotation->client->name }}</a></dd></div>
                    @if ($quotation->lead)
                        <div><dt>{{ __('quotations.lead') }}</dt><dd><a href="{{ route('leads.show', $quotation->lead) }}"><bdi dir="ltr">{{ $quotation->lead->number }}</bdi></a></dd></div>
                    @endif
                    @if ($quotation->service)
                        <div><dt>{{ __('quotations.service') }}</dt><dd>{{ $quotation->service->name }}</dd></div>
                    @endif
                    @if ($quotation->location || $quotation->emirate)
                        <div><dt>{{ __('quotations.location') }}</dt><dd>{{ $quotation->location }}@if ($quotation->emirate) {{ $quotation->emirate->name }}@endif</dd></div>
                    @endif
                    <div><dt>{{ __('quotations.created_by') }}</dt><dd>{{ $quotation->creator?->name }} · <bdi dir="ltr">{{ fmt_datetime($quotation->created_at) }}</bdi></dd></div>
                    @if ($quotation->notes)
                        <div><dt>{{ __('quotations.notes') }}</dt><dd class="text-pre">{{ $quotation->notes }}</dd></div>
                    @endif
                </dl>
            </x-panel>

            @if ($revisions->count() > 1)
                <x-panel :title="__('quotations.revisions')">
                    <ul class="contact-list">
                        @foreach ($revisions as $revision)
                            <li>
                                <a href="{{ route('quotations.show', $revision) }}" @class(['fw-semibold' => $revision->is($quotation)])><bdi dir="ltr">{{ $revision->number }}</bdi></a>
                                @include('quotations.partials.status', ['row' => $revision])
                            </li>
                        @endforeach
                    </ul>
                </x-panel>
            @endif

            <x-attachments :model="$quotation" :action="route('quotations.attachments.store', $quotation)" />
            <x-activity-tab :subject="$quotation" />
        </div>
    </div>
@endsection
