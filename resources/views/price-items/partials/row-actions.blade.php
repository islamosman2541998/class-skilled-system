<button type="button" class="btn-icon" title="{{ __('table.edit') }}" aria-label="{{ __('table.edit') }}"
    data-price-edit="{{ json_encode($row->only(['id', 'description_ar', 'description_en', 'service_id', 'unit_id', 'default_price', 'is_active'])) }}">
    <x-icon name="pencil" :size="16" />
</button>
