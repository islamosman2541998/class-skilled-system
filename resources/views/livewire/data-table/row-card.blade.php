@php
    $cardColumns = array_values(array_filter($visibleColumns, fn ($column) => $column->showOnCard)) ?: array_slice($visibleColumns, 0, 3);
@endphp
<div @class(['row-card', 'is-selected' => in_array((string) $row->getKey(), $selected, true)]) wire:key="card-{{ $row->getKey() }}">
    <div class="row-card-head">
        @if ($selectable)
            <input type="checkbox" class="form-check-input" wire:model.live="selected" value="{{ $row->getKey() }}" aria-label="{{ __('table.select_row') }}">
        @endif
        <div class="row-card-body">
            @foreach ($cardColumns as $index => $column)
                <div @class(['row-card-line', 'row-card-title' => $index === 0])>
                    @if ($index > 0)
                        <span class="row-card-label">{{ $column->label() }}</span>
                    @endif
                    <span @class(['num' => $column->alignsEnd && $index > 0])>{{ $column->render($row) }}</span>
                </div>
            @endforeach
        </div>
    </div>
    @if ($hasActions)
        @include('livewire.data-table.row-actions')
    @endif
</div>
