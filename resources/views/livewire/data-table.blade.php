<div class="data-table">
    <div class="panel panel-flush">
        @include('livewire.data-table.toolbar')

        @if ($filterDefinitions || $hasDateRange)
            @include('livewire.data-table.filters')
        @endif

        @if ($activeFilters)
            @include('livewire.data-table.active-filters')
        @endif

        <div class="panel-body" wire:loading.class="is-loading" wire:target="search, filters, sort, setDatePreset, setDateRange, clearFilters, removeFilter, perPage, gotoPage, nextPage, previousPage, trashed">
            @if ($rows->isEmpty())
                <x-empty-state :message="__('table.empty')">
                    @if ($activeFilters)
                        <button type="button" class="btn btn-outline btn-sm" wire:click="clearFilters">{{ __('table.clear_filters') }}</button>
                    @endif
                </x-empty-state>
            @else
                {{-- Table: medium screens and up --}}
                <div class="table-responsive d-none d-md-block">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                @foreach ($columns as $column)
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
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="row-{{ $row->getKey() }}">
                                    @foreach ($columns as $column)
                                        <td @class(['num' => $column->alignsEnd])>{{ $column->render($row) }}</td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                        @if ($totals)
                            <tfoot>
                                <tr>
                                    @foreach ($columns as $index => $column)
                                        <td @class(['num' => $column->alignsEnd])>
                                            @if (isset($totals[$column->key]))
                                                {{ $totals[$column->key] }}
                                            @elseif ($index === 0)
                                                {{ __('table.total') }}
                                            @endif
                                        </td>
                                    @endforeach
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
                            @foreach ($columns as $column)
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
