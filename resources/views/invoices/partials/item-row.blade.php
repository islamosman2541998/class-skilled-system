<div class="repeater-row" data-repeater-row>
    <div class="form-grid v-item">
        <input name="items[{{ $i }}][description_ar]" value="{{ $item['description_ar'] ?? '' }}" class="form-control" placeholder="{{ __('finance.description_ar') }}" aria-label="{{ __('finance.description_ar') }}" required>
        <select name="items[{{ $i }}][unit_id]" class="form-select" aria-label="{{ __('finance.unit') }}">
            <option value=""></option>
            @foreach ($units as $unit)
                <option value="{{ $unit->id }}" @selected((int) ($item['unit_id'] ?? 0) === $unit->id)>{{ $unit->name }}</option>
            @endforeach
        </select>
        <input name="items[{{ $i }}][quantity]" value="{{ $item['quantity'] ?? '1' }}" class="form-control text-end" aria-label="{{ __('finance.quantity') }}" dir="ltr" inputmode="decimal" required data-qty>
        <input name="items[{{ $i }}][unit_price]" value="{{ $item['unit_price'] ?? '0' }}" class="form-control text-end" aria-label="{{ __('finance.unit_price') }}" dir="ltr" inputmode="decimal" required data-price>
        <output class="q-line-total" dir="ltr" data-line></output>
    </div>
    <input type="hidden" name="items[{{ $i }}][description_en]" value="{{ $item['description_en'] ?? '' }}">
    <button type="button" class="btn-icon btn-icon-danger" data-row-remove aria-label="{{ __('finance.remove_item') }}"><x-icon name="x" /></button>
</div>
