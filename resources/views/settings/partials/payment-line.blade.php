<div class="repeater-row" data-line>
    <div class="form-grid form-grid-4">
        <input name="payment_templates[{{ $t }}][lines][{{ $l }}][label_ar]" value="{{ $line['label_ar'] ?? '' }}" class="form-control" placeholder="{{ __('settings.templates.label_ar') }}" aria-label="{{ __('settings.templates.label_ar') }}" maxlength="190" required>
        <input name="payment_templates[{{ $t }}][lines][{{ $l }}][label_en]" value="{{ $line['label_en'] ?? '' }}" class="form-control" placeholder="{{ __('settings.templates.label_en') }}" aria-label="{{ __('settings.templates.label_en') }}" maxlength="190" dir="ltr">
        <input name="payment_templates[{{ $t }}][lines][{{ $l }}][percent]" value="{{ $line['percent'] ?? '' }}" class="form-control text-end" placeholder="%" aria-label="{{ __('settings.templates.percent') }}" dir="ltr" inputmode="decimal" data-percent required>
    </div>
    <button type="button" class="btn-icon btn-icon-danger" data-line-remove aria-label="{{ __('settings.templates.remove_line') }}"><x-icon name="x" /></button>
</div>
