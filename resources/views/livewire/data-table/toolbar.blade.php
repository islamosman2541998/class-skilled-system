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

            @include('livewire.data-table.saved-filters')
        @endif

        @include('livewire.data-table.columns-menu')

        @if ($canExport)
            <button type="button" class="btn btn-outline btn-sm" wire:click="export" wire:loading.attr="disabled" wire:target="export">
                <x-icon name="download" :size="16" />
                {{ __('table.export') }}
            </button>
        @endif

        @if ($canDelete && $rows->total() > 0)
            <div class="dropdown">
                <button type="button" class="btn btn-outline btn-sm" data-bs-toggle="dropdown" aria-expanded="false" aria-label="{{ __('table.more') }}">
                    <x-icon name="dots" :size="16" />
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li>
                        <button type="button" class="dropdown-item text-danger"
                            x-on:click="confirmAction({ title: @js(__('table.confirm_delete_all')), text: @js(__('table.confirm_delete_all_text', ['count' => $rows->total()])), confirmText: @js(__('table.delete_all')), danger: true, requireText: '{{ $rows->total() }}' }).then((ok) => ok && $wire.deleteAll())">
                            {{ __('table.delete_all') }}
                        </button>
                    </li>
                </ul>
            </div>
        @endif
    </div>
</div>
