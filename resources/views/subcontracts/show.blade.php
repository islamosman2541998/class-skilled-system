@extends('layouts.app')

@php
    $billed = $subcontract->billed();
@endphp

@section('title', $subcontract->number)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('subcontracts.index') }}">{{ __('resources.subcontracts.subcontracts') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$subcontract->number" :status="$subcontract->status->label()" :status-color="$subcontract->status->color()">
        <x-slot:actions>
            @can('subcontracts.update')
                <a href="{{ route('subcontracts.edit', $subcontract) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('resources.subcontracts.edit') }}</a>
            @endcan
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('resources.subcontracts.value') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($subcontract->value) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('resources.subcontracts.billed_total') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($billed) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('resources.subcontracts.remaining') }}</span><span class="figure-value"><bdi dir="ltr">{{ money(bcsub((string) $subcontract->value, $billed, 2)) }}</bdi></span></div>
        </x-slot:figures>
    </x-record-header>

    <div class="row g-3">
        <div class="col-xl-8">
            <x-panel :title="__('resources.subcontracts.payments')" class="panel-flush">
                @if ($subcontract->payments->isEmpty())
                    <x-empty-state :message="__('resources.subcontracts.no_payments')" icon="file-text" />
                @else
                    <div class="table-responsive">
                        <table class="table q-view mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">{{ __('resources.subcontracts.payment_label') }}</th>
                                    <th scope="col" class="num">{{ __('resources.subcontracts.amount') }}</th>
                                    <th scope="col">{{ __('resources.subcontracts.due_date') }}</th>
                                    <th scope="col">{{ __('resources.subcontracts.bill') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($subcontract->payments as $payment)
                                    <tr>
                                        <td>{{ $payment->label() }}</td>
                                        <td class="num"><bdi dir="ltr">{{ money($payment->amount) }}</bdi></td>
                                        <td><bdi dir="ltr">{{ $payment->due_date ? fmt_date($payment->due_date) : '' }}</bdi></td>
                                        <td>
                                            @if ($payment->purchase)
                                                <a href="{{ route('purchases.show', $payment->purchase) }}"><bdi dir="ltr">{{ $payment->purchase->number }}</bdi></a>
                                                <x-status-badge :color="$payment->purchase->status->color()">{{ $payment->purchase->status->label() }}</x-status-badge>
                                            @elseif ($subcontract->status !== \App\Enums\SubcontractStatus::Cancelled)
                                                @can('purchases.create')
                                                    <form method="POST" action="{{ route('subcontracts.bill', [$subcontract, $payment]) }}" data-confirm="{{ __('resources.subcontracts.bill_confirm') }}">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline btn-sm">{{ __('resources.subcontracts.bill') }}</button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-panel>

            <x-panel :title="__('resources.subcontracts.scope')">
                <p class="text-pre mb-0">{{ $subcontract->scope }}</p>
            </x-panel>
        </div>
        <div class="col-xl-4">
            <x-panel :title="__('resources.subcontracts.subcontract')">
                <dl class="detail-list">
                    <div><dt>{{ __('resources.subcontracts.subcontractor') }}</dt><dd><a href="{{ route('suppliers.show', $subcontract->supplier) }}">{{ $subcontract->supplier->name }}</a></dd></div>
                    <div><dt>{{ __('resources.subcontracts.project') }}</dt><dd><a href="{{ route('projects.show', [$subcontract->project, 'tab' => 'purchases']) }}"><bdi dir="ltr">{{ $subcontract->project->number }}</bdi></a> · {{ $subcontract->project->title }}</dd></div>
                    <div><dt>{{ __('resources.subcontracts.start_date') }}</dt><dd><bdi dir="ltr">{{ fmt_date($subcontract->start_date) }}</bdi></dd></div>
                    @if ($subcontract->end_date)<div><dt>{{ __('resources.subcontracts.end_date') }}</dt><dd><bdi dir="ltr">{{ fmt_date($subcontract->end_date) }}</bdi></dd></div>@endif
                    <div><dt>{{ __('resources.purchases.vat') }}</dt><dd><bdi dir="ltr">{{ rtrim(rtrim((string) $subcontract->vat_rate, '0'), '.') ?: '0' }}%</bdi></dd></div>
                    @if ($subcontract->notes)<div><dt>{{ __('resources.subcontracts.notes') }}</dt><dd class="text-pre">{{ $subcontract->notes }}</dd></div>@endif
                </dl>
            </x-panel>
            <x-activity-tab :subject="$subcontract" />
        </div>
    </div>
@endsection
