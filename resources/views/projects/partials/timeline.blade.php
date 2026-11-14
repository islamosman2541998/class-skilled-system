{{-- Simple timeline (spec §8.7): planned and actual bars per stage across the project dates. Pure CSS. --}}
@php
    $dates = collect([$project->start_date, $project->planned_end_date, $project->actual_end_date])
        ->merge($stages->flatMap(fn ($s) => [$s->planned_start, $s->planned_end, $s->actual_start, $s->actual_end ?? ($s->actual_start ? today() : null)]))
        ->filter();
    $from = $dates->min();
    $to = $dates->max();
    $span = $from && $to ? max(1, $from->diffInDays($to)) : null;
    $bar = function ($start, $end) use ($from, $span) {
        if (! $span || ! $start) {
            return null;
        }
        $end ??= $start;
        $offset = round($from->diffInDays($start) / $span * 100, 2);
        $width = max(1.5, round($start->diffInDays($end) / $span * 100, 2));

        return 'inset-inline-start: '.$offset.'%; inline-size: '.min($width, 100 - $offset).'%';
    };
@endphp

@if ($span)
    <x-panel :title="__('projects.timeline')" class="timeline-panel">
        <div class="tl-legend">
            <span><i class="tl-key tl-planned"></i>{{ __('projects.planned') }}</span>
            <span><i class="tl-key tl-actual"></i>{{ __('projects.actual') }}</span>
            <bdi dir="ltr" class="ms-auto text-muted small">{{ fmt_date($from) }} – {{ fmt_date($to) }}</bdi>
        </div>
        <ul class="tl-rows">
            @foreach ($stages as $stage)
                <li>
                    <span class="tl-label">{{ $stage->name }}</span>
                    <span class="tl-track">
                        @if ($style = $bar($stage->planned_start, $stage->planned_end))
                            <span class="tl-bar tl-planned" style="{{ $style }}"></span>
                        @endif
                        @if ($style = $bar($stage->actual_start, $stage->actual_end ?? ($stage->actual_start ? today() : null)))
                            <span @class(['tl-bar tl-actual', 'is-late' => $stage->isLate()]) style="{{ $style }}"></span>
                        @endif
                    </span>
                </li>
            @endforeach
        </ul>
    </x-panel>
@endif
