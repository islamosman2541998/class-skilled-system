@php
    $hasActions = $canDelete || $canRestore || $canForceDelete || $rowActionsView !== null
        || ($rows->isNotEmpty() && $editUrl($rows->first()) !== null);
    $pageSelected = $pageIds !== [] && array_diff($pageIds, $selected) === [];
@endphp
<div class="data-table">
    <div class="panel panel-flush">
        @if ($showTrash)
            <div class="dt-tabs" role="tablist">
                <button type="button" role="tab" @class(['dt-tab', 'is-active' => ! $trashed]) aria-selected="{{ $trashed ? 'false' : 'true' }}" wire:click="$set('trashed', false)">{{ __('table.records') }}</button>
                <button type="button" role="tab" @class(['dt-tab', 'is-active' => $trashed]) aria-selected="{{ $trashed ? 'true' : 'false' }}" wire:click="$set('trashed', true)">{{ __('table.trash') }}</button>
            </div>
        @endif

        @include('livewire.data-table.toolbar')

        @if ($filterDefinitions || $hasDateRange)
            @include('livewire.data-table.filters')
        @endif

        @if ($activeFilters)
            @include('livewire.data-table.active-filters')
        @endif

        @if ($selected)
            @include('livewire.data-table.bulk-bar')
        @endif

        <div class="panel-body" wire:loading.class="is-loading" wire:target="search, filters, sort, setDatePreset, setDateRange, clearFilters, removeFilter, perPage, gotoPage, nextPage, previousPage, trashed, applySavedFilter, deleteOne, deleteSelected, deleteAll, restore, restoreSelected, forceDelete, toggleColumn">
            @if ($rows->isEmpty())
                <x-empty-state :message="__('table.empty')">
                    @if ($activeFilters)
                        <button type="button" class="btn btn-outline btn-sm" wire:click="clearFilters">{{ __('table.clear_filters') }}</button>
                    @else
                        {{ $empty ?? '' }}
                    @endif
                </x-empty-state>
            @else
                {{-- Table: medium screens and up --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                @if ($selectable)
                                    <th scope="col" class="dt-check">
                                        <input type="checkbox" class="form-check-input" wire:click="togglePageSelection" @checked($pageSelected) aria-label="{{ __('table.select_page') }}">
                                    </th>
                                @endif
                                @foreach ($visibleColumns as $column)
                                    <th scope="col" @class(['num' => $column->alignsEnd])
                                        @if ($sortBy === $column->key) aria-sort="{{ $sortDir === 'asc' ? 'ascending' : 'descending' }}" @endif>
                                        @if ($column->sortColumn)
                                            <button type="button" class="th-sort" wire:click="sort('{{ $column->key }}')">
                                                {{ $column->label() }}
                                                @if ($sortBy === $column->key)
                                                    <x-icon :name="$sortDir === 'asc' ? 'arrow-up' : 'arrow-down'" :size="14" />
                                                @else
                                                    <x-icon name="arrows-sort" :size="14" class="th-sort-idle" />
                                                @endif
                                            </button>
                                        @else
                                            {{ $column->label() }}
                                        @endif
                                    </th>
                                @endforeach
                                @if ($hasActions)
                                    <th scope="col" class="dt-actions-head"><span class="visually-hidden">{{ __('table.actions') }}</span></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="row-{{ $row->getKey() }}" @class(['is-selected' => in_array((string) $row->getKey(), $selected, true)])>
                                    @if ($selectable)
                                        <td class="dt-check">
                                            <input type="checkbox" class="form-check-input" wire:model.live="selected" value="{{ $row->getKey() }}" aria-label="{{ __('table.select_row') }}">
                                        </td>
                                    @endif
                                    @foreach ($visibleColumns as $column)
                                        <td @class(['num' => $column->alignsEnd])>{{ $column->render($row) }}</td>
                                    @endforeach
                                    @if ($hasActions)
                                        <td class="dt-actions">@include('livewire.data-table.row-actions')</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($totals)
                            <tfoot>
                                <tr>
                                    @if ($selectable)
                                        <td></td>
                                    @endif
                                    @foreach ($visibleColumns as $index => $column)
                                        <td @class(['num' => $column->alignsEnd])>
                                            @if (isset($totals[$column->key]))
                                                {{ $totals[$column->key] }}
                                            @elseif ($index === 0)
                                                {{ __('table.total') }}
                                            @endif
                                        </td>
                                    @endforeach
                                    @if ($hasActions)
                                        <td></td>
                                    @endif
                                </tr>
                            </tfoot>
                        @endif
                    </table>
                </div>

                {{-- Cards: small screens --}}
                <div class="d-md-none row-cards">
                    @foreach ($rows as $row)
                        @include('livewire.data-table.row-card')
                    @endforeach

                    @if ($totals)
                        <div class="row-card row-card-total">
                            @foreach ($visibleColumns as $column)
                                @if (isset($totals[$column->key]))
                                    <div class="row-card-line">
                                        <span class="row-card-label">{{ __('table.total') }} · {{ $column->label() }}</span>
                                        <span class="num">{{ $totals[$column->key] }}</span>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif
        </div>

        @include('livewire.data-table.pagination')
    </div>
</div>
