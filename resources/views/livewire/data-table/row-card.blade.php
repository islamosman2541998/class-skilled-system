@php
    $cardColumns = array_values(array_filter($columns, fn ($column) => $column->showOnCard)) ?: array_slice($columns, 0, 3);
@endphp
<div class="row-card" wire:key="card-{{ $row->getKey() }}">
    @foreach ($cardColumns as $index => $column)
        <div @class(['row-card-line', 'row-card-title' => $index === 0])>
            @if ($index > 0)
                <span class="row-card-label">{{ $column->label() }}</span>
            @endif
            <span @class(['num' => $column->alignsEnd && $index > 0])>{{ $column->render($row) }}</span>
        </div>
    @endforeach
</div>
