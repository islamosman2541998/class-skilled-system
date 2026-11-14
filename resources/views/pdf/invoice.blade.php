@extends('pdf.layout')

@php
    // Sections render before the layout, so the physical sides are worked out here too.
    $start = $language === 'en' ? 'left' : 'right';
    $end = $language === 'en' ? 'right' : 'left';
    $dir = fn (string $line) => preg_match('/\p{Arabic}/u', $line) ? 'rtl' : 'ltr';
    // In both languages a label that reads the same (IBAN) is shown once.
    $label = fn (string $key) => $language === 'both'
        ? implode(' / ', array_unique([__('pdf.'.$key, [], 'ar'), __('pdf.'.$key, [], 'en')]))
        : __('pdf.'.$key, [], $language);
    $text = fn (?string $ar, ?string $en) => match ($language) {
        'en' => filled($en) ? $en : $ar,
        'both' => filled($en) && $en !== $ar ? $ar."\n".$en : $ar,
        default => $ar,
    };
    $amount = fn ($value) => money($value, false);
    $finance = app(\App\Settings\FinanceSettings::class);
    $symbol = $language === 'en' ? $finance->currency_symbol_en : $finance->currency_symbol_ar;
    $company = app(\App\Settings\CompanySettings::class);
    $templates = app(\App\Settings\TemplateSettings::class);
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    $stamp = $company->stamp_path && $disk->exists($company->stamp_path) ? $disk->path($company->stamp_path) : null;
    $signature = $company->signature_path && $disk->exists($company->signature_path) ? $disk->path($company->signature_path) : null;
    $terms = $language === 'en' ? ($invoice->terms_en ?: $invoice->terms_ar) : ($language === 'both' ? trim($invoice->terms_ar."\n\n".$invoice->terms_en) : $invoice->terms_ar);
    $footer = $language === 'en' ? $templates->invoice_footer_en : $templates->invoice_footer_ar;
    $clientName = $text($invoice->client->name_ar, $invoice->client->name_en);
    $rate = rtrim(rtrim((string) $invoice->vat_rate, '0'), '.');
@endphp

@section('content')
    <h1>{{ $label('tax_invoice') }}</h1>
    @if ($invoice->status === \App\Enums\InvoiceStatus::Cancelled)
        <div style="color: #b42318; font-weight: bold;">{{ $label('cancelled') }}</div>
    @endif

    <table>
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <div class="box">
                    <div class="muted">{{ $label('client') }}</div>
                    @foreach (preg_split('/\R/u', $clientName) as $line)
                        <div dir="{{ $dir($line) }}" style="font-weight: bold; text-align: {{ $start }};">{{ $line }}</div>
                    @endforeach
                    @if ($invoice->client->address)
                        <div dir="{{ $dir($invoice->client->address) }}" style="text-align: {{ $start }};">{{ $invoice->client->address }}</div>
                    @endif
                    <div dir="ltr" style="text-align: {{ $start }};">{{ $invoice->client->phone }}</div>
                    @if ($invoice->client->trn)
                        <div>{{ $label('trn') }}: <span dir="ltr">{{ $invoice->client->trn }}</span></div>
                    @endif
                </div>
            </td>
            <td style="width: 3%;"></td>
            <td style="width: 42%; vertical-align: top;">
                <table class="grid">
                    <tr><th>{{ $label('invoice_no') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ $invoice->number }}</td></tr>
                    <tr><th>{{ $label('invoice_date') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ fmt_date($invoice->issue_date) }}</td></tr>
                    <tr><th>{{ $label('due_date') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ fmt_date($invoice->due_date) }}</td></tr>
                    <tr><th>{{ $label('project') }}</th><td><span dir="ltr">{{ $invoice->project->number }}</span></td></tr>
                    @if ($invoice->replaces_id && $invoice->replaces)
                        <tr><th>{{ $label('replaces') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ $invoice->replaces->number }}</td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <h2>{{ $text($invoice->project->title_ar, $invoice->project->title_en) }}</h2>

    <table class="grid" style="margin-top: 3mm;">
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th>{{ $label('description') }}</th>
                <th style="width: 10%;">{{ $label('unit') }}</th>
                <th style="width: 11%;" class="num">{{ $label('quantity') }}</th>
                <th style="width: 14%;" class="num">{{ $label('price') }}</th>
                <th style="width: 16%;" class="num">{{ $label('net') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $index => $item)
                <tr>
                    <td dir="ltr">{{ $index + 1 }}</td>
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
        </tbody>
    </table>

    <table class="totals" style="margin-top: 2mm;">
        <tr><td style="width: 55%;"></td><td>{{ $label('net') }}</td><td class="num" dir="ltr">{{ $amount($invoice->subtotal) }} {{ $symbol }}</td></tr>
        <tr><td></td><td>{{ $label('vat') }} <span dir="ltr">({{ $rate }}%)</span></td><td class="num" dir="ltr">{{ $amount($invoice->vat_amount) }} {{ $symbol }}</td></tr>
        <tr class="grand"><td></td><td>{{ $label('grand_total') }}</td><td class="num" dir="ltr">{{ $amount($invoice->total) }} {{ $symbol }}</td></tr>
    </table>

    @if ($company->bank_iban)
        <h2>{{ $label('bank_details') }}</h2>
        <table class="grid">
            @if ($company->bank_name)<tr><th style="width: 30%;">{{ $label('bank') }}</th><td>{{ $company->bank_name }}</td></tr>@endif
            @if ($company->bank_account_name)<tr><th>{{ $label('account_name') }}</th><td>{{ $company->bank_account_name }}</td></tr>@endif
            <tr><th>{{ $label('iban') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ $company->bank_iban }}</td></tr>
        </table>
    @endif

    @if ($terms)
        <h2>{{ $label('terms') }}</h2>
        @foreach (preg_split('/\R/u', $terms) as $line)
            <div dir="{{ $dir($line) }}" style="text-align: {{ $start }};">{{ $line !== '' ? $line : ' ' }}</div>
        @endforeach
    @endif

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
