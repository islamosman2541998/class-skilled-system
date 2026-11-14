<div class="payment-template" data-template="{{ $t }}">
    <div class="form-grid">
        <x-field :name="'payment_templates['.$t.'][name_ar]'" :label="__('settings.templates.template_name_ar')" :for="'pt-'.$t.'-name-ar'" required>
            <input id="pt-{{ $t }}-name-ar" name="payment_templates[{{ $t }}][name_ar]" value="{{ $template['name_ar'] ?? '' }}" class="form-control" maxlength="100" required>
        </x-field>
        <x-field :name="'payment_templates['.$t.'][name_en]'" :label="__('settings.templates.template_name_en')" :for="'pt-'.$t.'-name-en'">
            <input id="pt-{{ $t }}-name-en" name="payment_templates[{{ $t }}][name_en]" value="{{ $template['name_en'] ?? '' }}" class="form-control" maxlength="100" dir="ltr">
        </x-field>
    </div>
    <div data-lines>
        @foreach ($template['lines'] ?? [] as $l => $line)
            @include('settings.partials.payment-line', ['t' => $t, 'l' => $l, 'line' => $line])
        @endforeach
    </div>
    <div class="invalid-feedback d-block" data-error-for="payment_templates.{{ $t }}">@error('payment_templates.'.$t){{ $message }}@enderror</div>
    <div class="payment-template-footer">
        <button type="button" class="btn btn-outline btn-sm" data-line-add><x-icon name="plus" :size="16" /> {{ __('settings.templates.add_line') }}</button>
        <span class="ms-auto">{{ __('settings.templates.total') }}: <bdi dir="ltr"><span data-sum></span>%</bdi></span>
        <button type="button" class="btn btn-outline btn-sm text-danger" data-template-remove>{{ __('settings.templates.remove_template') }}</button>
    </div>
</div>
