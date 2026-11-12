@php
    $properties = $row->properties?->toArray() ?? [];
    $old = (array) ($properties['old'] ?? []);
    $new = (array) ($properties['attributes'] ?? []);
    $keys = array_values(array_unique([...array_keys($new), ...array_keys($old)]));
    $extra = array_diff_key($properties, ['old' => 1, 'attributes' => 1]);
    $show = fn ($value) => is_array($value) ? implode(__('app.comma'), array_map(fn ($item) => is_scalar($item) ? (string) $item : json_encode($item, JSON_UNESCAPED_UNICODE), $value))
        : (is_bool($value) ? ($value ? __('table.yes') : __('table.no')) : (string) $value);
    $numeric = array_is_list($new) && array_is_list($old);
@endphp
@if ($keys || $extra)
    <details class="changes">
        <summary>
            @if ($keys && ! $numeric)
                {{ trans_choice('activity.fields_changed', count($keys), ['count' => count($keys)]) }}
            @else
                {{ __('activity.details') }}
            @endif
        </summary>
        <dl class="changes-list">
            @foreach ($extra as $name => $value)
                <dt>{{ $name }}</dt>
                <dd><bdi>{{ $show($value) }}</bdi></dd>
            @endforeach

            @if ($numeric)
                @if ($old)
                    <dt>{{ __('activity.before') }}</dt>
                    <dd><bdi>{{ $show($old) }}</bdi></dd>
                @endif
                @if ($new)
                    <dt>{{ __('activity.after') }}</dt>
                    <dd><bdi>{{ $show($new) }}</bdi></dd>
                @endif
            @else
                @foreach ($keys as $name)
                    <dt>{{ $name }}</dt>
                    <dd>
                        @if (array_key_exists($name, $old))
                            <del><bdi>{{ $show($old[$name]) }}</bdi></del>
                        @endif
                        @if (array_key_exists($name, $new))
                            <ins><bdi>{{ $show($new[$name]) }}</bdi></ins>
                        @endif
                    </dd>
                @endforeach
            @endif
        </dl>
    </details>
@endif
