@extends('pdf.layout')

@php
    $start = $language === 'en' ? 'left' : 'right';
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
    $date = fn (?string $value) => $value ? implode('/', array_reverse(explode('-', substr($value, 0, 10)))) : '';
    $amount = fn ($value) => $value === null || bccomp((string) $value, '0', 2) === 0 ? '' : money((string) $value, false);
    $entries = ['purchase' => 'purchase_invoice', 'voucher' => 'payment_voucher', 'opening' => 'opening_balance'];
@endphp

@section('content')
    <h1>{{ $label('statement_of_account') }}</h1>

    <table class="grid">
        <tr><th style="width: 30%;">{{ $label('supplier') }}</th><td>{{ $text($supplier->name_ar, $supplier->name_en) }}</td></tr>
        <tr>
            <th>{{ $label('period') }}</th>
            <td dir="ltr" style="text-align: {{ $start }};">
                @if ($from || $to)
                    {{ $from ? fmt_date($from) : '' }} – {{ $to ? fmt_date($to) : '' }}
                @else
                    {{ $label('all_dates') }}
                @endif
            </td>
        </tr>
    </table>

    <table class="grid" style="margin-top: 5mm;">
        <thead>
            <tr>
                <th style="width: 16%;">{{ $label('date') }}</th>
                <th>{{ $label('document') }}</th>
                <th style="width: 17%;">{{ $label('paid') }}</th>
                <th style="width: 17%;">{{ $label('purchased') }}</th>
                <th style="width: 18%;">{{ $label('balance') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($lines as $line)
                <tr>
                    <td dir="ltr" style="text-align: {{ $start }};">{{ $date($line->entry_date) }}</td>
                    <td>{{ $label($entries[$line->entry]) }} @if ($line->number)<span dir="ltr">{{ $line->number }}</span>@endif</td>
                    <td class="num" dir="ltr">{{ $amount($line->debit) }}</td>
                    <td class="num" dir="ltr">{{ $amount($line->credit) }}</td>
                    <td class="num" dir="ltr">{{ money((string) $line->balance, false) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2">{{ $label('total') }}</th>
                <th class="num" dir="ltr">{{ money($debit, false) }}</th>
                <th class="num" dir="ltr">{{ money($credit, false) }}</th>
                <th></th>
            </tr>
            <tr>
                <th colspan="4">{{ $label('closing_balance') }}</th>
                <th class="num" dir="ltr">{{ money($closing, false) }} {{ $symbol }}</th>
            </tr>
        </tfoot>
    </table>
@endsection
