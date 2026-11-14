{{--
    Fixed summary on top of a record page (spec §4.3): title, status, key figures and actions.
    figures slot: <div class="figure"><span class="figure-label">…</span><span class="figure-value">…</span></div> items.
--}}
@props(['title', 'status' => null, 'statusColor' => 'neutral', 'subtitle' => null])
<section {{ $attributes->class(['record-header']) }}>
    <div class="record-header-top">
        <div class="record-header-title">
            <h1 class="page-title">{{ $title }}</h1>
            @if ($status)
                <x-status-badge :color="$statusColor">{{ $status }}</x-status-badge>
            @endif
            @if ($subtitle)
                <p class="record-header-subtitle">{{ $subtitle }}</p>
            @endif
        </div>
        @isset($actions)
            <div class="page-actions">{{ $actions }}</div>
        @endisset
    </div>
    @isset($figures)
        <div class="record-figures">{{ $figures }}</div>
    @endisset
</section>
