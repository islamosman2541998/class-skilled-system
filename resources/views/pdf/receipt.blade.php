@extends('pdf.layout')

@php
    $start = $language === 'en' ? 'left' : 'right';
    $end = $language === 'en' ? 'right' : 'left';
    $dir = fn (string $line) => preg_match('/\p{Arabic}/u', $line) ? 'rtl' : 'ltr';
    // In both languages a label that reads the same is shown once.
    $label = fn (string $key) => $language === 'both'
        ? implode(' / ', array_unique([__('pdf.'.$key, [], 'ar'), __('pdf.'.$key, [], 'en')]))
        : __('pdf.'.$key, [], $language);
    $text = fn (?string $ar, ?string $en) => match ($language) {
        'en' => filled($en) ? $en : $ar,
        'both' => filled($en) && $en !== $ar ? $ar.' / '.$en : $ar,
        default => $ar,
    };
    $finance = app(\App\Settings\FinanceSettings::class);
    $symbol = $language === 'en' ? $finance->currency_symbol_en : $finance->currency_symbol_ar;
    $unallocated = $receipt->unallocated();
@endphp

@section('content')
    <h1>{{ $label('receipt_voucher') }}</h1>
    @if (! $receipt->isActive())
        <div style="color: #b42318; font-weight: bold;">{{ $label('cancelled') }}</div>
    @endif

    <table class="grid">
        <tr><th style="width: 30%;">{{ $label('number') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ $receipt->number }}</td></tr>
        <tr><th>{{ $label('date') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ fmt_date($receipt->receipt_date) }}</td></tr>
        <tr><th>{{ $label('received_from') }}</th><td>{{ $text($receipt->client->name_ar, $receipt->client->name_en) }}</td></tr>
        <tr><th>{{ $label('the_sum_of') }}</th><td dir="ltr" style="text-align: {{ $start }}; font-weight: bold;">{{ money($receipt->amount, false) }} {{ $symbol }}</td></tr>
        <tr><th>{{ $label('method') }}</th><td>{{ $text($receipt->paymentMethod->name_ar, $receipt->paymentMethod->name_en) }}</td></tr>
        @if ($receipt->reference)
            <tr><th>{{ $label('reference') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ $receipt->reference }}</td></tr>
        @endif
        @if ($receipt->cheque_date)
            <tr><th>{{ $label('cheque_date') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ fmt_date($receipt->cheque_date) }}</td></tr>
        @endif
        @if ($receipt->project)
            <tr><th>{{ $label('project') }}</th><td><span dir="ltr">{{ $receipt->project->number }}</span> · {{ $text($receipt->project->title_ar, $receipt->project->title_en) }}</td></tr>
        @endif
    </table>

    <h2>{{ $label('against') }}</h2>
    <table class="grid">
        @foreach ($receipt->allocations as $allocation)
            <tr>
                <td>{{ $label('tax_invoice') }} <span dir="ltr">{{ $allocation->invoice->number }}</span></td>
                <td class="num" dir="ltr" style="width: 30%;">{{ money($allocation->amount, false) }} {{ $symbol }}</td>
            </tr>
        @endforeach
        @if (bccomp($unallocated, '0', 2) > 0)
            <tr>
                <td>{{ $label('advance') }}</td>
                <td class="num" dir="ltr" style="width: 30%;">{{ money($unallocated, false) }} {{ $symbol }}</td>
            </tr>
        @endif
    </table>

    @if ($receipt->notes)
        <h2>{{ $label('notes') }}</h2>
        @foreach (preg_split('/\R/u', $receipt->notes) as $line)
            <div dir="{{ $dir($line) }}" style="text-align: {{ $start }};">{{ $line }}</div>
        @endforeach
    @endif

    <table style="margin-top: 14mm;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div style="font-weight: bold;">{{ $label('received_by') }}</div>
                <div class="muted" style="margin-top: 3mm;">{{ $receipt->receiver->name }}</div>
                <div class="muted" style="margin-top: 8mm;">{{ $label('receiver_signature') }}: ____________________</div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div style="font-weight: bold;">{{ $label('stamp') }}</div>
            </td>
        </tr>
    </table>
@endsection
