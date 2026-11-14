@extends('pdf.layout')

@php
    $start = $language === 'en' ? 'left' : 'right';
    $end = $language === 'en' ? 'right' : 'left';
    $label = fn (string $key, array $params = []) => $language === 'both'
        ? __('pdf.'.$key, $params, 'ar').' / '.__('pdf.'.$key, $params, 'en')
        : __('pdf.'.$key, $params, $language);
    $text = fn (?string $ar, ?string $en) => match ($language) {
        'en' => filled($en) ? $en : $ar,
        'both' => filled($en) && $en !== $ar ? $ar.' / '.$en : $ar,
        default => $ar,
    };
    $paragraphs = $language === 'both'
        ? [__('pdf.handover_text', [], 'ar'), __('pdf.handover_text', [], 'en')]
        : [__('pdf.handover_text', [], $language)];
    $dir = fn (string $line) => preg_match('/\p{Arabic}/u', $line) ? 'rtl' : 'ltr';
@endphp

@section('content')
    <h1>{{ $label('handover_record') }}</h1>

    <table class="grid">
        <tr><th style="width: 30%;">{{ $label('project') }}</th><td>{{ $text($project->title_ar, $project->title_en) }} — <span dir="ltr">{{ $project->number }}</span></td></tr>
        <tr><th>{{ $label('client') }}</th><td>{{ $text($project->client->name_ar, $project->client->name_en) }}</td></tr>
        @if ($project->location)
            <tr><th>{{ $label('location') }}</th><td>{{ $project->location }}</td></tr>
        @endif
        <tr><th>{{ $label('handover_date') }}</th><td dir="ltr" style="text-align: {{ $start }};">{{ fmt_date($handover->handover_date) }}</td></tr>
        <tr><th>{{ $label('warranty') }}</th><td>{{ $label('warranty_months', ['months' => $handover->warranty_months]) }} — {{ $label('warranty_end') }} <span dir="ltr">{{ fmt_date($handover->warranty_end) }}</span></td></tr>
    </table>

    @if ($project->stages->isNotEmpty())
        <h2>{{ $label('scope') }}</h2>
        <table class="grid">
            @foreach ($project->stages as $index => $stage)
                <tr>
                    <td style="width: 8%;" dir="ltr">{{ $index + 1 }}</td>
                    <td>{{ $text($stage->name_ar, $stage->name_en) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div style="margin-top: 5mm;">
        @foreach ($paragraphs as $paragraph)
            <p dir="{{ $dir($paragraph) }}" style="text-align: {{ $start }};">{{ $paragraph }}</p>
        @endforeach
    </div>

    @if ($handover->notes)
        <h2>{{ $label('notes') }}</h2>
        @foreach (preg_split('/\R/u', $handover->notes) as $line)
            <div dir="{{ $dir($line) }}" style="text-align: {{ $start }};">{{ $line }}</div>
        @endforeach
    @endif

    <table style="margin-top: 14mm;">
        <tr>
            <td style="width: 50%; vertical-align: top;">
                <div style="font-weight: bold;">{{ $label('contractor') }}</div>
                <div class="muted" style="margin-top: 3mm;">{{ $label('name') }}: {{ $project->manager->name }}</div>
                <div class="muted" style="margin-top: 8mm;">{{ $label('signature') }}: ____________________</div>
            </td>
            <td style="width: 50%; vertical-align: top;">
                <div style="font-weight: bold;">{{ $label('owner') }}</div>
                <div class="muted" style="margin-top: 3mm;">{{ $label('name') }}: ____________________</div>
                <div class="muted" style="margin-top: 8mm;">{{ $label('signature') }}: ____________________</div>
            </td>
        </tr>
    </table>
@endsection
