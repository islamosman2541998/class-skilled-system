<div class="repeater-row" data-repeater-row>
    <div class="form-grid form-grid-4">
        <input name="contacts[{{ $i }}][name]" value="{{ $contact['name'] ?? '' }}" class="form-control" placeholder="{{ __('clients.contact_name') }}" aria-label="{{ __('clients.contact_name') }}" maxlength="190" required>
        <input name="contacts[{{ $i }}][position]" value="{{ $contact['position'] ?? '' }}" class="form-control" placeholder="{{ __('clients.position') }}" aria-label="{{ __('clients.position') }}" maxlength="190">
        <input name="contacts[{{ $i }}][phone]" value="{{ $contact['phone'] ?? '' }}" class="form-control" placeholder="{{ __('app.phone') }}" aria-label="{{ __('app.phone') }}" dir="ltr" inputmode="tel">
        <input name="contacts[{{ $i }}][email]" value="{{ $contact['email'] ?? '' }}" class="form-control" placeholder="{{ __('app.email') }}" aria-label="{{ __('app.email') }}" dir="ltr" type="email">
    </div>
    <button type="button" class="btn-icon btn-icon-danger" data-repeater-remove aria-label="{{ __('clients.remove') }}"><x-icon name="x" /></button>
</div>
