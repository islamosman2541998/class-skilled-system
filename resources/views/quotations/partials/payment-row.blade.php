<div class="repeater-row" data-payment>
    <div class="form-grid form-grid-4">
        <input name="payments[{{ $p }}][label_ar]" value="{{ $payment['label_ar'] ?? '' }}" class="form-control" placeholder="{{ __('quotations.payment_label') }}" aria-label="{{ __('quotations.payment_label') }}" maxlength="190" required data-field="label_ar">
        <input name="payments[{{ $p }}][label_en]" value="{{ $payment['label_en'] ?? '' }}" class="form-control" placeholder="{{ __('quotations.payment_label_en') }}" aria-label="{{ __('quotations.payment_label_en') }}" maxlength="190" dir="ltr" data-field="label_en">
        <input name="payments[{{ $p }}][percent]" value="{{ $payment['percent'] ?? '' }}" class="form-control text-end" placeholder="%" aria-label="{{ __('quotations.percent') }}" dir="ltr" inputmode="decimal" required data-field="percent" data-calc>
        <output class="form-control-plaintext text-end" data-payment-amount dir="ltr"></output>
    </div>
    <button type="button" class="btn-icon btn-icon-danger" data-payment-remove aria-label="{{ __('settings.templates.remove_line') }}"><x-icon name="x" /></button>
</div>
