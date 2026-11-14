<div class="q-item" data-item>
    <button type="button" class="q-handle btn-icon" data-sort-handle aria-label="{{ __('quotations.move') }}"><x-icon name="grip-vertical" :size="16" /></button>
    <input type="hidden" name="items[{{ $i }}][price_item_id]" value="{{ $item['price_item_id'] ?? '' }}" data-field="price_item_id">
    <div class="q-item-fields">
        <input name="items[{{ $i }}][section_ar]" value="{{ $item['section_ar'] ?? '' }}" class="form-control form-control-sm q-section" placeholder="{{ __('quotations.section') }}" aria-label="{{ __('quotations.section') }}" maxlength="190" data-field="section_ar">
        <textarea name="items[{{ $i }}][description_ar]" class="form-control q-desc" rows="1" placeholder="{{ __('quotations.description_ar') }}" aria-label="{{ __('quotations.description_ar') }}" required data-field="description_ar">{{ $item['description_ar'] ?? '' }}</textarea>
        <textarea name="items[{{ $i }}][description_en]" class="form-control form-control-sm q-desc-en" rows="1" placeholder="{{ __('quotations.description_en') }}" aria-label="{{ __('quotations.description_en') }}" dir="ltr" data-field="description_en">{{ $item['description_en'] ?? '' }}</textarea>
        <select name="items[{{ $i }}][unit_id]" class="form-select q-unit" aria-label="{{ __('quotations.unit') }}" data-field="unit_id">
            <option value=""></option>
            @foreach ($units as $unit)
                <option value="{{ $unit->id }}" @selected((int) ($item['unit_id'] ?? 0) === $unit->id)>{{ $unit->name }}</option>
            @endforeach
        </select>
        <input name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? '1' }}" class="form-control text-end q-qty" aria-label="{{ __('quotations.quantity') }}" dir="ltr" inputmode="decimal" required data-field="quantity" data-calc>
        <input name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] ?? '0' }}" class="form-control text-end q-price" aria-label="{{ __('quotations.unit_price') }}" dir="ltr" inputmode="decimal" required data-field="unit_price" data-calc>
        <output class="q-line-total" data-line-total dir="ltr"></output>
    </div>
    <button type="button" class="btn-icon btn-icon-danger" data-item-remove aria-label="{{ __('quotations.remove_item') }}"><x-icon name="x" /></button>
</div>
