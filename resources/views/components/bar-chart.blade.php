@props(['labels' => [], 'axisLabels' => null, 'series' => [], 'caption' => ''])
{{--
    Grouped bar chart (spec §9.1) in plain HTML so text keeps its size on any screen.
    labels: category labels in time order; axisLabels: shorter ones for the axis (optional). series: list of ['label' => string, 'color' => css color,
    'values' => list<string>] (money). One axis from zero, thin bars with rounded tops on the
    baseline, a 2px gap between bars, a legend, a tooltip on each bar and the same numbers as a
    table. Time follows the reading direction (right to left in Arabic).
--}}
@php
    $max = 0.0;
    foreach ($series as $item) {
        foreach ($item['values'] as $value) {
            $max = max($max, (float) $value);
        }
    }
    // A round top so the grid lines read as plain numbers.
    $step = $max > 0 ? pow(10, floor(log10($max / 4))) : 1;
    foreach ([1, 2, 2.5, 5, 10] as $m) {
        if ($step * $m * 4 >= $max) {
            $step *= $m;
            break;
        }
    }
    $top = max($step * 4, 1);
    $percent = fn ($value) => round(min((float) $value / $top, 1) * 100, 2);
@endphp

<figure class="bar-chart" role="group" aria-label="{{ $caption }}" {{ $attributes }}>
    <ul class="chart-legend">
        @foreach ($series as $item)
            <li><span class="chart-swatch" style="background: {{ $item['color'] }};"></span>{{ $item['label'] }}</li>
        @endforeach
    </ul>

    <div class="chart-body" aria-hidden="true">
        <div class="chart-plot">
            @for ($i = 4; $i >= 0; $i--)
                <div @class(['chart-gridline', 'is-baseline' => $i === 0]) style="bottom: {{ $i * 25 }}%;">
                    <span class="chart-tick"><bdi dir="ltr">{{ number_format($top / 4 * $i, 0, '.', ',') }}</bdi></span>
                </div>
            @endfor

            <div class="chart-groups">
                @foreach ($labels as $i => $label)
                    <div class="chart-group">
                        @foreach ($series as $item)
                            @php($value = $item['values'][$i] ?? '0')
                            <div class="chart-hit" title="{{ $label }} · {{ $item['label'] }}: {{ money($value) }}">
                                <span class="chart-bar" style="height: {{ $percent($value) }}%; background: {{ $item['color'] }};"></span>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
        </div>

        <div class="chart-labels">
            @foreach ($axisLabels ?? $labels as $label)
                <span><bdi dir="ltr">{{ $label }}</bdi></span>
            @endforeach
        </div>
    </div>

    <details class="chart-table">
        <summary>{{ __('home.show_table') }}</summary>
        <div class="table-responsive">
            <table class="table table-sm">
                <thead>
                    <tr>
                        <th>{{ __('home.month') }}</th>
                        @foreach ($series as $item)
                            <th class="text-end">{{ $item['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($labels as $i => $label)
                        <tr>
                            <td><bdi dir="ltr">{{ $label }}</bdi></td>
                            @foreach ($series as $item)
                                <td class="text-end"><bdi dir="ltr">{{ money($item['values'][$i] ?? '0') }}</bdi></td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
</figure>
