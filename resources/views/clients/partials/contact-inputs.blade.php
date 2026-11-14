<input name="name" value="{{ $contact?->name }}" class="form-control" placeholder="{{ __('clients.contact_name') }}" aria-label="{{ __('clients.contact_name') }}" maxlength="190" required>
<input name="position" value="{{ $contact?->position }}" class="form-control" placeholder="{{ __('clients.position') }}" aria-label="{{ __('clients.position') }}" maxlength="190">
<input name="phone" value="{{ $contact?->phone }}" class="form-control" placeholder="{{ __('app.phone') }}" aria-label="{{ __('app.phone') }}" dir="ltr" inputmode="tel">
<input name="email" value="{{ $contact?->email }}" class="form-control" placeholder="{{ __('app.email') }}" aria-label="{{ __('app.email') }}" dir="ltr" type="email">
