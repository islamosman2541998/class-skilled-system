<div class="dt-bulk" role="status">
    <span class="dt-bulk-count">{{ __('table.selected_count', ['count' => count($selected)]) }}</span>

    @if ($canExport)
        <button type="button" class="btn btn-outline btn-sm" wire:click="exportSelected">{{ __('table.export_selected') }}</button>
    @endif

    @if ($canRestore)
        <button type="button" class="btn btn-outline btn-sm" wire:click="restoreSelected">{{ __('table.restore_selected') }}</button>
    @endif

    @if ($canDelete)
        <button type="button" class="btn btn-danger btn-sm"
            x-on:click="confirmAction({ title: @js(__('table.confirm_delete_selected', ['count' => count($selected)])), text: @js(__('table.confirm_delete_text')), confirmText: @js(__('table.delete')), danger: true }).then((ok) => ok && $wire.deleteSelected())">
            {{ __('table.delete_selected') }}
        </button>
    @endif

    <button type="button" class="btn btn-link btn-sm" wire:click="clearSelection">{{ __('table.clear_selection') }}</button>
</div>
