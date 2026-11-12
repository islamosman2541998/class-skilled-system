<button type="button" class="btn-icon" title="{{ __('table.edit') }}" aria-label="{{ __('table.edit') }}"
    data-lookup-edit="{{ json_encode($row->only(['id', 'name_ar', 'name_en', 'parent_id', 'color', 'is_active'])) }}">
    <x-icon name="pencil" :size="16" />
</button>
