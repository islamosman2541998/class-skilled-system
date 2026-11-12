<div class="dt-chips">
    @foreach ($activeFilters as $chip)
        <span class="dt-chip" wire:key="chip-{{ $chip['key'] }}">
            <span class="dt-chip-label">{{ $chip['label'] }}</span>
            <bdi>{{ $chip['value'] }}</bdi>
            <button type="button" class="dt-chip-remove" wire:click="removeFilter('{{ $chip['key'] }}')" aria-label="{{ __('table.remove_filter') }} {{ $chip['label'] }}">
                <x-icon name="x" :size="14" />
            </button>
        </span>
    @endforeach
    <button type="button" class="btn btn-link btn-sm" wire:click="clearFilters">{{ __('table.clear_filters') }}</button>
</div>
