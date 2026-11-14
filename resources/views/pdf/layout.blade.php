{{--
    Shared frame of printed documents (spec §5.10): company header and footer from the settings.
    mPDF has no logical CSS properties, so $start / $end hold the physical side for the language.
    Expects $language (ar | en | both) and the "content" section.
--}}
@php
    $company = app(\App\Settings\CompanySettings::class);
    $appearance = app(\App\Settings\AppearanceSettings::class);
    $rtl = $language !== 'en';
    $start = $rtl ? 'right' : 'left';
    $end = $rtl ? 'left' : 'right';
    $disk = \Illuminate\Support\Facades\Storage::disk('public');
    $image = fn (?string $path) => $path && $disk->exists($path) ? $disk->path($path) : null;
    $logo = $image($company->logo_path);
    $companyName = match ($language) {
        'en' => $company->name_en,
        'both' => $company->name_ar.' — '.$company->name_en,
        default => $company->name_ar,
    };
    $address = $language === 'en' ? $company->address_en : $company->address_ar;
@endphp
<html dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <style>
        body { font-size: 9.5pt; color: {{ $appearance->color_ink }}; line-height: 1.45; }
        h1 { font-size: 15pt; color: {{ $appearance->color_navy }}; margin: 0 0 2mm; }
        h2 { font-size: 11pt; color: {{ $appearance->color_navy }}; margin: 5mm 0 2mm; }
        .muted { color: {{ $appearance->color_muted }}; }
        .header td { vertical-align: top; }
        .company { font-size: 11pt; font-weight: bold; color: {{ $appearance->color_navy }}; }
        table { border-collapse: collapse; width: 100%; }
        .grid td, .grid th { border: 0.2mm solid {{ $appearance->color_line }}; padding: 1.6mm 2mm; }
        .grid th { background: {{ $appearance->color_paper }}; color: {{ $appearance->color_navy }}; font-weight: bold; text-align: {{ $start }}; }
        .num { text-align: {{ $end }}; white-space: nowrap; }
        .section td { background: {{ $appearance->color_paper }}; font-weight: bold; color: {{ $appearance->color_navy }}; }
        .totals td { padding: 1.2mm 2mm; }
        .grand td { font-weight: bold; font-size: 11pt; border-top: 0.4mm solid {{ $appearance->color_navy }}; }
        .box { border: 0.2mm solid {{ $appearance->color_line }}; padding: 2.5mm; }
        .terms { white-space: pre-line; }
        .rule { border-top: 0.3mm solid {{ $appearance->color_navy }}; margin: 3mm 0; }
    </style>
</head>
<body>
    <htmlpagefooter name="footer">
        <table style="font-size: 8pt; color: {{ $appearance->color_muted }};">
            <tr>
                <td style="text-align: {{ $start }};">
                    @if ($company->bank_name)
                        {{ $company->bank_name }} · {{ $company->bank_account_name }} · <span dir="ltr">{{ $company->bank_iban }}</span>
                    @endif
                </td>
                <td style="text-align: {{ $end }};" dir="ltr">{PAGENO} / {nbpg}</td>
            </tr>
        </table>
    </htmlpagefooter>
    <sethtmlpagefooter name="footer" value="on" />

    <table class="header">
        <tr>
            <td style="width: 65%; text-align: {{ $start }};">
                <div class="company">{{ $companyName }}</div>
                @if ($address)<div class="muted">{{ $address }}</div>@endif
                <div class="muted" dir="ltr" style="text-align: {{ $start }};">
                    {{ implode(' · ', array_filter([...(array) $company->phones, $company->email])) }}
                </div>
                @if ($company->trn)
                    <div class="muted">{{ $label('trn') }}: <span dir="ltr">{{ $company->trn }}</span></div>
                @endif
            </td>
            <td style="width: 35%; text-align: {{ $end }};">
                @if ($logo)<img src="{{ $logo }}" style="height: 18mm;">@endif
            </td>
        </tr>
    </table>
    <div class="rule"></div>

    @yield('content')
</body>
</html>
