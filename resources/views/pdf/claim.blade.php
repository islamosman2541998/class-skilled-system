@extends('pdf.layout')

@php
    // Sections render before the layout, so the physical sides are worked out here too.
    $start = $language === 'en' ? 'left' : 'right';
    $dir = fn (string $line) => preg_match('/\p{Arabic}/u', $line) ? 'rtl' : 'ltr';
    $label = fn (string $key) => $language === 'both'
        ? implode(' / ', array_unique([__('pdf.'.$key, [], 'ar'), __('pdf.'.$key, [], 'en')]))
        : __('pdf.'.$key, [], $language);
    $text = fn (?string $ar, ?string $en) => match ($language) {
        'en' => filled($en) ? $en : $ar,
        'both' => filled($en) && $en !== $ar ? $ar."\n".$en : $ar,
        default => $ar,
    };
    $amount = fn ($value) => money($value, false);
    $qty = fn ($value) => rtrim(rtrim((string) $value, '0'), '.') ?: '0';
    $finance = app(\App\Settings\FinanceSettings::class);
    $symbol = $language === 'en' ? $finance->currency_symbol_en : $finance->currency_symbol_ar;
    $company = app(\App\Settings\CompanySettings::class);
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    $stamp = $company->stamp_path && $disk->exists($company->stamp_path) ? $disk->path($company->stamp_path) : null;
    $signature = $company->signature_path && $disk->exists($company->signature_path) ? $disk->path($company->signature_path) : null;
    $clientName = $text($claim->client->name_ar, $claim->client->name_en);
    $rate = rtrim(rtrim((string) $claim->vat_rate, '0'), '.');
    $retention = rtrim(rtrim((string) $claim->retention_percent, '0'), '.') ?: '0';
@endphp

@section('content')
    <h1>{{ $label('progress_claim') }}</h1>

    <table>
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="box">
                    <div class="muted">{{ $label('client') }}</div>
                    @foreach (preg_split('/\R/u', $clientName) as $line)
                        <div dir="{{ $dir($line) }}" style="font-weight: bold; text-align: {{ $start }};">{{ $line }}</div>
                    @endforeach
                    @if ($claim->client->trn)
                        <div>{{ $label('trn') }}: <span dir="ltr">{{ $claim->client->trn }}</span></div>
                    @endif
                </div>
            </td>
            <td style="width: 3%;"></td>
            <td style="width: 42%; vertical-align: top;">
                <table class="grid">
                    <tr><th>{{ $label('claim_no') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ $claim->number }}</td></tr>
                    <tr><th>{{ $label('period') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ fmt_date($claim->period_from) }} - {{ fmt_date($claim->period_to) }}</td></tr>
                    <tr><th>{{ $label('project') }}</th><td><span dir="ltr">{{ $claim->project->number }}</span></td></tr>
                </table>
            </td>
        </tr>
    </table>

    <h2>{{ $text($claim->project->title_ar, $claim->project->title_en) }}</h2>

    <table class="grid" style="margin-top: 3mm; font-size: 8pt;">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th>{{ $label('description') }}</th>
                <th style="width: 7%;">{{ $label('unit') }}</th>
                <th style="width: 8%;" class="num">{{ $label('contract_quantity') }}</th>
                <th style="width: 9%;" class="num">{{ $label('price') }}</th>
                <th style="width: 8%;" class="num">{{ $label('previous') }}</th>
                <th style="width: 8%;" class="num">{{ $label('current') }}</th>
                <th style="width: 8%;" class="num">{{ $label('cumulative') }}</th>
                <th style="width: 11%;" class="num">{{ $label('current_amount') }}</th>
                <th style="width: 11%;" class="num">{{ $label('cumulative_amount') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($claim->lines as $index => $line)
                <tr>
                    <td dir="ltr">{{ $index + 1 }}</td>
                    <td>
                        @foreach (preg_split('/\R/u', $text($line->description_ar, $line->description_en)) as $part)
                            <div dir="{{ $dir($part) }}" style="text-align: {{ $start }};">{{ $part }}</div>
                        @endforeach
                    </td>
                    <td>{{ $line->unit ? $text($line->unit->name_ar, $line->unit->name_en) : '' }}</td>
                    <td class="num" dir="ltr">{{ $qty($line->contract_quantity) }}</td>
                    <td class="num" dir="ltr">{{ $amount($line->unit_price) }}</td>
                    <td class="num" dir="ltr">{{ $qty($line->previous_quantity) }}</td>
                    <td class="num" dir="ltr">{{ $qty($line->current_quantity) }}</td>
                    <td class="num" dir="ltr">{{ $qty($line->cumulative_quantity) }}</td>
                    <td class="num" dir="ltr">{{ $amount($line->current_amount) }}</td>
                    <td class="num" dir="ltr">{{ $amount($line->cumulative_amount) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top: 2mm;">
        <tr><td style="width: 45%;"></td><td>{{ $label('work_previous') }}</td><td class="num" dir="ltr">{{ $amount($claim->work_previous) }} {{ $symbol }}</td></tr>
        <tr><td></td><td>{{ $label('work_cumulative') }}</td><td class="num" dir="ltr">{{ $amount($claim->work_cumulative) }} {{ $symbol }}</td></tr>
        <tr><td></td><td>{{ $label('work_current') }}</td><td class="num" dir="ltr">{{ $amount($claim->work_current) }} {{ $symbol }}</td></tr>
        @if (bccomp((string) $claim->discount_amount, '0', 2) !== 0)
            <tr><td></td><td>{{ $label('contract_discount') }}</td><td class="num" dir="ltr">-{{ $amount($claim->discount_amount) }} {{ $symbol }}</td></tr>
            <tr><td></td><td>{{ $label('gross_after_discount') }}</td><td class="num" dir="ltr">{{ $amount($claim->gross_amount) }} {{ $symbol }}</td></tr>
        @endif
        <tr><td></td><td>{{ $label('retention') }} <span dir="ltr">({{ $retention }}%)</span></td><td class="num" dir="ltr">-{{ $amount($claim->retention_amount) }} {{ $symbol }}</td></tr>
        @if (bccomp((string) $claim->advance_recovery, '0', 2) !== 0)
            <tr><td></td><td>{{ $label('advance_recovery') }}</td><td class="num" dir="ltr">-{{ $amount($claim->advance_recovery) }} {{ $symbol }}</td></tr>
        @endif
        <tr><td></td><td>{{ $label('net_due') }}</td><td class="num" dir="ltr">{{ $amount($claim->net_amount) }} {{ $symbol }}</td></tr>
        <tr><td></td><td>{{ $label('vat') }} <span dir="ltr">({{ $rate }}%)</span></td><td class="num" dir="ltr">{{ $amount($claim->vat_amount) }} {{ $symbol }}</td></tr>
        <tr class="grand"><td></td><td>{{ $label('grand_total') }}</td><td class="num" dir="ltr">{{ $amount($claim->total) }} {{ $symbol }}</td></tr>
    </table>

    <table style="margin-top: 10mm;">
        <tr>
            <td style="width: 33%; text-align: center;">
                <div class="muted">{{ $label('signature') }}</div>
                @if ($signature)<img src="{{ $signature }}" style="height: 18mm;">@endif
            </td>
            <td style="width: 34%; text-align: center;">
                <div class="muted">{{ $label('stamp') }}</div>
                @if ($stamp)<img src="{{ $stamp }}" style="height: 24mm;">@endif
            </td>
            <td style="width: 33%; text-align: center;">
                <div class="muted">{{ $label('client_approval') }}</div>
            </td>
        </tr>
    </table>
@endsection
