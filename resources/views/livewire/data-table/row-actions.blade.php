@php
    $rowKey = $row->getKey();
    $rowEditUrl = $editUrl($row);
@endphp
<div class="dt-row-actions">
    @if ($rowActionsView && ! $trashed)
        @include($rowActionsView, ['row' => $row])
    @endif

    @if ($rowEditUrl)
        <a href="{{ $rowEditUrl }}" class="btn-icon" title="{{ __('table.edit') }}" aria-label="{{ __('table.edit') }}">
            <x-icon name="pencil" :size="16" />
        </a>
    @endif

    @if ($canDelete)
        <button type="button" class="btn-icon btn-icon-danger" title="{{ __('table.delete') }}" aria-label="{{ __('table.delete') }}"
            x-on:click="confirmAction({ title: @js(__('table.confirm_delete')), text: @js(__('table.confirm_delete_text')), confirmText: @js(__('table.delete')), danger: true }).then((ok) => ok && $wire.deleteOne({{ \Illuminate\Support\Js::from($rowKey) }}))">
            <x-icon name="trash" :size="16" />
        </button>
    @endif

    @if ($canRestore)
        <button type="button" class="btn-icon" title="{{ __('table.restore') }}" aria-label="{{ __('table.restore') }}"
            x-on:click="confirmAction({ title: @js(__('table.confirm_restore')), confirmText: @js(__('table.restore')) }).then((ok) => ok && $wire.restore({{ \Illuminate\Support\Js::from($rowKey) }}))">
            <x-icon name="restore" :size="16" />
        </button>
    @endif

    @if ($canForceDelete)
        <button type="button" class="btn-icon btn-icon-danger" title="{{ __('table.force_delete') }}" aria-label="{{ __('table.force_delete') }}"
            x-on:click="confirmAction({ title: @js(__('table.confirm_force_delete')), text: @js(__('table.confirm_force_delete_text')), confirmText: @js(__('table.force_delete')), danger: true, requireText: @js(__('table.delete')) }).then((ok) => ok && $wire.forceDelete({{ \Illuminate\Support\Js::from($rowKey) }}))">
            <x-icon name="trash-x" :size="16" />
        </button>
    @endif
</div>
