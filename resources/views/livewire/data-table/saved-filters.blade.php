<div class="dropdown">
    <button type="button" class="btn btn-outline btn-sm" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="{{ __('table.saved_filters') }}">
        <x-icon name="bookmark" :size="16" />
    </button>
    <div class="dropdown-menu dropdown-menu-end dt-menu" wire:ignore.self>
        <div class="dt-menu-title">{{ __('table.saved_filters') }}</div>

        @forelse ($savedFilters as $saved)
            <div class="dt-menu-row" wire:key="saved-{{ $saved->id }}">
                <button type="button" class="dropdown-item" wire:click="applySavedFilter({{ $saved->id }})">{{ $saved->name }}</button>
                <button type="button" class="dt-chip-remove" wire:click="deleteSavedFilter({{ $saved->id }})" aria-label="{{ __('table.delete') }} {{ $saved->name }}">
                    <x-icon name="x" :size="14" />
                </button>
            </div>
        @empty
            <div class="dt-menu-empty">{{ __('table.no_saved_filters') }}</div>
        @endforelse

        @if ($activeFilters)
            <div class="dt-menu-form" x-data="{ name: '' }">
                <input type="text" class="form-control form-control-sm" x-model="name" maxlength="80" placeholder="{{ __('table.filter_name') }}" aria-label="{{ __('table.filter_name') }}"
                    x-on:keydown.enter.prevent="if (name.trim()) { $wire.saveFilter(name); name = '' }">
                <button type="button" class="btn btn-primary btn-sm" x-bind:disabled="! name.trim()" x-on:click="$wire.saveFilter(name); name = ''">{{ __('app.save') }}</button>
            </div>
        @endif
    </div>
</div>
