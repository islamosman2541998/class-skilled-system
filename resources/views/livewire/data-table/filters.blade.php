<div class="collapse dt-filters" id="dt-filters-{{ $this->getId() }}" wire:ignore.self>
    <div class="dt-filters-grid">
        @foreach ($filterDefinitions as $filter)
            <div wire:key="filter-{{ $filter->key }}">
                <label class="form-label" for="f-{{ $this->getId() }}-{{ $filter->key }}">{{ $filter->label() }}</label>

                @switch($filter->type())
                    @case('select')
                        <select class="form-select form-select-sm" id="f-{{ $this->getId() }}-{{ $filter->key }}" wire:model.live="filters.{{ $filter->key }}">
                            <option value="">{{ __('table.all') }}</option>
                            @foreach ($filter->resolveOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @break

                    @case('multi')
                        <select class="form-select form-select-sm" id="f-{{ $this->getId() }}-{{ $filter->key }}" wire:model.live="filters.{{ $filter->key }}" multiple size="3">
                            @foreach ($filter->resolveOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @break

                    @case('boolean')
                        <select class="form-select form-select-sm" id="f-{{ $this->getId() }}-{{ $filter->key }}" wire:model.live="filters.{{ $filter->key }}">
                            <option value="">{{ __('table.all') }}</option>
                            <option value="1">{{ __('table.yes') }}</option>
                            <option value="0">{{ __('table.no') }}</option>
                        </select>
                        @break

                    @case('range')
                        <div class="d-flex gap-2">
                            <input type="number" step="any" inputmode="decimal" class="form-control form-control-sm" id="f-{{ $this->getId() }}-{{ $filter->key }}" wire:model.live.debounce.400ms="filters.{{ $filter->key }}.min" placeholder="{{ __('table.from') }}" dir="ltr">
                            <input type="number" step="any" inputmode="decimal" class="form-control form-control-sm" wire:model.live.debounce.400ms="filters.{{ $filter->key }}.max" placeholder="{{ __('table.to') }}" aria-label="{{ $filter->label() }} {{ __('table.to') }}" dir="ltr">
                        </div>
                        @break

                    @case('dates')
                        <div class="d-flex gap-2">
                            <input type="date" class="form-control form-control-sm" id="f-{{ $this->getId() }}-{{ $filter->key }}" wire:model.live="filters.{{ $filter->key }}.from" aria-label="{{ $filter->label() }} {{ __('table.from') }}" dir="ltr">
                            <input type="date" class="form-control form-control-sm" wire:model.live="filters.{{ $filter->key }}.to" aria-label="{{ $filter->label() }} {{ __('table.to') }}" dir="ltr">
                        </div>
                        @break
                @endswitch
            </div>
        @endforeach

        @if ($hasDateRange)
            <div class="dt-filter-date">
                <label class="form-label" for="f-{{ $this->getId() }}-date">{{ __('table.period') }}</label>
                <div class="d-flex gap-2">
                    {{-- Replaced as a whole when the range changes so the picker always shows the server state. --}}
                    <div class="flex-grow-1" wire:ignore wire:key="date-{{ $dateFrom }}-{{ $dateTo }}">
                        <input type="text" class="form-control form-control-sm" id="f-{{ $this->getId() }}-date" data-date-range
                            value="{{ $dateFrom && $dateTo ? $dateFrom.' - '.$dateTo : '' }}" placeholder="{{ __('table.from') }} - {{ __('table.to') }}" autocomplete="off"
                            x-on:date-range="$wire.setDateRange($event.detail.from, $event.detail.to)">
                    </div>
                    <div class="dropdown">
                        <button type="button" class="btn btn-outline btn-sm" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('table.presets') }}">
                            <x-icon name="calendar" :size="16" />
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            @foreach (\App\Livewire\DataTable::DATE_PRESETS as $preset)
                                <li><button type="button" class="dropdown-item" wire:click="setDatePreset('{{ $preset }}')">{{ __('table.preset_'.$preset) }}</button></li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
