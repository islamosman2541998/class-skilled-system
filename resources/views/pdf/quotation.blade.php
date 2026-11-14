@extends('pdf.layout')

@php
    // Sections render before the layout, so the physical sides are worked out here too.
    $start = $language === 'en' ? 'left' : 'right';
    $end = $language === 'en' ? 'right' : 'left';
    // A line without Arabic letters is laid out left to right, so English punctuation stays in place.
    $dir = fn (string $line) => preg_match('/\p{Arabic}/u', $line) ? 'rtl' : 'ltr';
    $label = fn (string $key) => $language === 'both'
        ? __('pdf.'.$key, [], 'ar').' / '.__('pdf.'.$key, [], 'en')
        : __('pdf.'.$key, [], $language);
    $text = fn (?string $ar, ?string $en) => match ($language) {
        'en' => filled($en) ? $en : $ar,
        'both' => filled($en) && $en !== $ar ? $ar."\n".$en : $ar,
        default => $ar,
    };
    $amount = fn ($value) => money($value, false);
    $symbol = $language === 'en' ? app(\App\Settings\FinanceSettings::class)->currency_symbol_en : app(\App\Settings\FinanceSettings::class)->currency_symbol_ar;
    $sections = $quotation->items->groupBy(fn ($item) => (string) $item->section_ar);
    $company = app(\App\Settings\CompanySettings::class);
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    $stamp = $company->stamp_path && $disk->exists($company->stamp_path) ? $disk->path($company->stamp_path) : null;
    $signature = $company->signature_path && $disk->exists($company->signature_path) ? $disk->path($company->signature_path) : null;
    $terms = $language === 'en' ? ($quotation->terms_en ?: $quotation->terms_ar) : ($language === 'both' ? trim($quotation->terms_ar."\n\n".$quotation->terms_en) : $quotation->terms_ar);
    $clientName = $text($quotation->client->name_ar, $quotation->client->name_en);
@endphp

@section('content')
    <h1>{{ $label('quotation') }}</h1>

    <table>
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="box">
                    <div class="muted">{{ $label('client') }}</div>
                    @foreach (preg_split('/\R/u', $clientName) as $line)
                        <div dir="{{ $dir($line) }}" style="font-weight: bold; text-align: {{ $start }};">{{ $line }}</div>
                    @endforeach
                    <div dir="ltr" style="text-align: {{ $start }};">{{ $quotation->client->phone }}</div>
                    @if ($quotation->client->trn)
                        <div>{{ $label('trn') }}: <span dir="ltr">{{ $quotation->client->trn }}</span></div>
                    @endif
                </div>
            </td>
            <td style="width: 3%;"></td>
            <td style="width: 42%; vertical-align: top;">
                <table class="grid">
                    <tr><th>{{ $label('number') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ $quotation->number }}</td></tr>
                    <tr><th>{{ $label('date') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ fmt_date($quotation->issue_date) }}</td></tr>
                    <tr><th>{{ $label('valid_until') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ fmt_date($quotation->valid_until) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <h2>{{ $text($quotation->title_ar, $quotation->title_en) }}</h2>
    @if ($quotation->location || $quotation->emirate)
        <div class="muted">{{ $label('location') }}: {{ $quotation->location }} {{ $quotation->emirate ? $text($quotation->emirate->name_ar, $quotation->emirate->name_en) : '' }}</div>
    @endif

    <table class="grid" style="margin-top: 3mm;">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th>{{ $label('description') }}</th>
                <th style="width: 10%;">{{ $label('unit') }}</th>
                <th style="width: 11%;" class="num">{{ $label('quantity') }}</th>
                <th style="width: 13%;" class="num">{{ $label('price') }}</th>
                <th style="width: 15%;" class="num">{{ $label('total') }}</th>
            </tr>
        </thead>
        <tbody>
            @php($n = 0)
            @foreach ($sections as $section => $items)
                @if ($section !== '')
                    <tr class="section"><td colspan="6">{{ $text($section, $items->first()->section_en) }}</td></tr>
                @endif
                @foreach ($items as $item)
                    <tr>
                        <td dir="ltr">{{ ++$n }}</td>
                        <td>
                            @foreach (preg_split('/\R/u', $text($item->description_ar, $item->description_en)) as $line)
                                <div dir="{{ $dir($line) }}" style="text-align: {{ $start }};">{{ $line }}</div>
                            @endforeach
                        </td>
                        <td>{{ $item->unit ? $text($item->unit->name_ar, $item->unit->name_en) : '' }}</td>
                        <td class="num" dir="ltr">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                        <td class="num" dir="ltr">{{ $amount($item->unit_price) }}</td>
                        <td class="num" dir="ltr">{{ $amount($item->line_total) }}</td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <table class="totals" style="margin-top: 2mm;">
        <tr><td style="width: 60%;"></td><td>{{ $label('subtotal') }}</td><td class="num" dir="ltr">{{ $amount($quotation->subtotal) }} {{ $symbol }}</td></tr>
        @if ((float) $quotation->discount_amount > 0)
            <tr><td></td><td>{{ $label('discount') }}</td><td class="num" dir="ltr">{{ $amount($quotation->discount_amount) }} {{ $symbol }}</td></tr>
            <tr><td></td><td>{{ $label('after_discount') }}</td><td class="num" dir="ltr">{{ $amount($quotation->taxable_amount) }} {{ $symbol }}</td></tr>
        @endif
        <tr><td></td><td>{{ $label('vat') }} <span dir="ltr">({{ rtrim(rtrim($quotation->vat_rate, '0'), '.') }}%)</span></td><td class="num" dir="ltr">{{ $amount($quotation->vat_amount) }} {{ $symbol }}</td></tr>
        <tr class="grand"><td></td><td>{{ $label('grand_total') }}</td><td class="num" dir="ltr">{{ $amount($quotation->total) }} {{ $symbol }}</td></tr>
    </table>

    <h2>{{ $label('payments') }}</h2>
    <table class="grid">
        @foreach ($quotation->payments as $payment)
            <tr>
                <td>{{ $text($payment->label_ar, $payment->label_en) }}</td>
                <td class="num" dir="ltr" style="width: 12%;">{{ rtrim(rtrim($payment->percent, '0'), '.') }}%</td>
                <td class="num" dir="ltr" style="width: 22%;">{{ $amount($payment->amount) }} {{ $symbol }}</td>
            </tr>
        @endforeach
    </table>

    @if ($terms)
        <h2>{{ $label('terms') }}</h2>
        @foreach (preg_split('/\R/u', $terms) as $line)
            <div dir="{{ $dir($line) }}" style="text-align: {{ $start }};">{{ $line !== '' ? $line : ' ' }}</div>
        @endforeach
    @endif

    @php($footer = $language === 'en' ? app(\App\Settings\TemplateSettings::class)->quotation_footer_en : app(\App\Settings\TemplateSettings::class)->quotation_footer_ar)
    @if ($footer)
        <p class="muted" style="margin-top: 4mm;">{{ $footer }}</p>
    @endif

    <table style="margin-top: 10mm;">
        <tr>
            <td style="width: 50%; text-align: center;">
                <div class="muted">{{ $label('signature') }}</div>
                @if ($signature)<img src="{{ $signature }}" style="height: 18mm;">@endif
            </td>
            <td style="width: 50%; text-align: center;">
                <div class="muted">{{ $label('stamp') }}</div>
                @if ($stamp)<img src="{{ $stamp }}" style="height: 24mm;">@endif
            </td>
        </tr>
    </table>
@endsection
