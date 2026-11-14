@extends('layouts.app')

@section('title', $expense->number)

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('expenses.index') }}">{{ __('finance.expenses.expenses') }}</a></li>
        </ol>
    </nav>

    <x-record-header :title="$expense->number">
        <x-slot:actions>
            @can('expenses.update')
                <a href="{{ route('expenses.edit', $expense) }}" class="btn btn-outline"><x-icon name="pencil" :size="16" /> {{ __('finance.expenses.edit') }}</a>
            @endcan
        </x-slot:actions>
        <x-slot:figures>
            <div class="figure"><span class="figure-label">{{ __('finance.expenses.amount') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($expense->amount) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('finance.vat') }} <bdi dir="ltr">({{ rtrim(rtrim((string) $expense->vat_rate, '0'), '.') ?: '0' }}%)</bdi></span><span class="figure-value"><bdi dir="ltr">{{ money($expense->vat_amount) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('finance.total') }}</span><span class="figure-value"><bdi dir="ltr">{{ money($expense->total) }}</bdi></span></div>
            <div class="figure"><span class="figure-label">{{ __('finance.expenses.date') }}</span><span class="figure-value"><bdi dir="ltr">{{ fmt_date($expense->expense_date) }}</bdi></span></div>
        </x-slot:figures>
    </x-record-header>

    <div class="row g-3">
        <div class="col-xl-7">
            <x-panel :title="__('finance.expenses.expense')">
                <dl class="detail-list">
                    <div>
                        <dt>{{ __('finance.project') }}</dt>
                        <dd>
                            @if ($expense->project)
                                <a href="{{ route('projects.show', [$expense->project, 'tab' => 'expenses']) }}"><bdi dir="ltr">{{ $expense->project->number }}</bdi></a> · {{ $expense->project->title }}
                            @else
                                {{ __('finance.expenses.general') }}
                            @endif
                        </dd>
                    </div>
                    <div><dt>{{ __('finance.expenses.category') }}</dt><dd>{{ $expense->category->name }}</dd></div>
                    @if ($expense->supplier_name)
                        <div><dt>{{ __('finance.expenses.supplier') }}</dt><dd>{{ $expense->supplier_name }}</dd></div>
                    @endif
                    @if ($expense->supplier_invoice_no)
                        <div><dt>{{ __('finance.expenses.supplier_invoice_no') }}</dt><dd><bdi dir="ltr">{{ $expense->supplier_invoice_no }}</bdi></dd></div>
                    @endif
                    @if ($expense->paymentMethod)
                        <div><dt>{{ __('finance.receipts.method') }}</dt><dd>{{ $expense->paymentMethod->name }}</dd></div>
                    @endif
                    @if ($expense->payer)
                        <div><dt>{{ __('finance.expenses.paid_by') }}</dt><dd>{{ $expense->payer->name }}</dd></div>
                    @endif
                    @if ($expense->creator)
                        <div><dt>{{ __('finance.expenses.entered_by') }}</dt><dd>{{ $expense->creator->name }}</dd></div>
                    @endif
                </dl>
                @if ($expense->description)
                    <p class="text-pre mt-3 mb-0">{{ $expense->description }}</p>
                @endif
            </x-panel>
            <x-activity-tab :subject="$expense" />
        </div>
        <div class="col-xl-5">
            <x-attachments :model="$expense" :action="route('expenses.attachments.store', $expense)" />
        </div>
    </div>
@endsection
