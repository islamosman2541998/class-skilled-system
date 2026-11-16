@php($billed = (bool) ($payment['billed'] ?? false))
<div class="repeater-row" data-repeater-row>
    @if (! empty($payment['id']))
        <input type="hidden" name="payments[{{ $i }}][id]" value="{{ $payment['id'] }}">
    @endif
    <div class="form-grid v-item">
        <input name="payments[{{ $i }}][label_ar]" value="{{ $payment['label_ar'] ?? '' }}" class="form-control" placeholder="{{ __('resources.subcontracts.payment_label') }}" aria-label="{{ __('resources.subcontracts.payment_label') }}" maxlength="190" required @readonly($billed)>
        <input name="payments[{{ $i }}][amount]" value="{{ $payment['amount'] ?? '' }}" class="form-control text-end" placeholder="{{ __('resources.subcontracts.amount') }}" aria-label="{{ __('resources.subcontracts.amount') }}" dir="ltr" inputmode="decimal" required @readonly($billed)>
        <input name="payments[{{ $i }}][due_date]" value="{{ $payment['due_date'] ?? '' }}" class="form-control" aria-label="{{ __('resources.subcontracts.due_date') }}" data-date dir="ltr">
        @if ($billed)
            <span class="text-muted small">{{ __('resources.subcontracts.billed_line') }}</span>
        @endif
    </div>
    <input type="hidden" name="payments[{{ $i }}][label_en]" value="{{ $payment['label_en'] ?? '' }}">
    @unless ($billed)
        <button type="button" class="btn-icon btn-icon-danger" data-row-remove aria-label="{{ __('resources.subcontracts.remove_payment') }}"><x-icon name="x" /></button>
    @endunless
</div>
