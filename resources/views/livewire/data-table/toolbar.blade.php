<div class="dt-toolbar">
    @if ($hasSearch)
        <div class="dt-search">
            <x-icon name="search" :size="16" />
            <input type="search" class="form-control" wire:model.live.debounce.300ms="search" placeholder="{{ __('table.search') }}" aria-label="{{ __('table.search') }}">
        </div>
    @endif

    <div class="dt-tools">
        @if ($filterDefinitions || $hasDateRange)
            <button type="button" class="btn btn-outline btn-sm" data-bs-toggle="collapse" data-bs-target="#dt-filters-{{ $this->getId() }}" aria-expanded="false">
                <x-icon name="filter" :size="16" />
                {{ __('table.filters') }}
                @if (count($activeFilters))
                    <span class="dt-count">{{ count($activeFilters) }}</span>
                @endif
            </button>
        @endif
    </div>
</div>
