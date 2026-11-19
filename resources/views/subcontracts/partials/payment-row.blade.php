@php($billed = (bool) ($payment['billed'] ?? false))
<div class="repeater-row subcontract-payment-row" data-repeater-row>
    @if (! empty($payment['id']))
        <input type="hidden" name="payments[{{ $i }}][id]" value="{{ $payment['id'] }}">
    @endif
    <div class="subcontract-payment-grid">
        <x-field name="payments[{{ $i }}][label_ar]" :label="__('resources.subcontracts.payment_label')" for="payment-label-{{ $i }}" required>
            <input id="payment-label-{{ $i }}" name="payments[{{ $i }}][label_ar]" value="{{ $payment['label_ar'] ?? '' }}" class="form-control" maxlength="190" required @readonly($billed)>
        </x-field>
        <x-field name="payments[{{ $i }}][amount]" :label="__('resources.subcontracts.amount')" for="payment-amount-{{ $i }}" required>
            <input id="payment-amount-{{ $i }}" name="payments[{{ $i }}][amount]" value="{{ $payment['amount'] ?? '' }}" class="form-control text-end" dir="ltr" inputmode="decimal" required @readonly($billed)>
        </x-field>
        <x-field name="payments[{{ $i }}][due_date]" :label="__('resources.subcontracts.due_date')" for="payment-due-date-{{ $i }}">
            <input id="payment-due-date-{{ $i }}" name="payments[{{ $i }}][due_date]" value="{{ $payment['due_date'] ?? '' }}" class="form-control" data-date dir="ltr">
        </x-field>
        @if ($billed)
            <span class="text-muted small">{{ __('resources.subcontracts.billed_line') }}</span>
        @endif
    </div>
    <input type="hidden" name="payments[{{ $i }}][label_en]" value="{{ $payment['label_en'] ?? '' }}">
    @unless ($billed)
        <button type="button" class="btn-icon btn-icon-danger" data-row-remove aria-label="{{ __('resources.subcontracts.remove_payment') }}"><x-icon name="x" /></button>
    @endunless
</div>
