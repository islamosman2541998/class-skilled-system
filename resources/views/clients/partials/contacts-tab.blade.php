<x-panel :title="__('clients.contacts')">
    @if ($client->contacts->isEmpty())
        <x-empty-state :message="__('clients.no_contacts')" icon="address-book" />
    @else
        <ul class="contact-list">
            @foreach ($client->contacts as $contact)
                <li>
                    <div>
                        <div class="fw-semibold">{{ $contact->name }}</div>
                        <div class="text-muted small">
                            {{ $contact->position }}
                            @if ($contact->phone) <bdi dir="ltr">{{ $contact->phone }}</bdi> @endif
                            @if ($contact->email) <bdi dir="ltr">{{ $contact->email }}</bdi> @endif
                        </div>
                    </div>
                    @can('clients.update')
                        <div class="d-flex gap-1">
                            <button type="button" class="btn-icon" data-bs-toggle="collapse" data-bs-target="#contact-edit-{{ $contact->id }}" aria-label="{{ __('clients.edit_contact') }}"><x-icon name="pencil" /></button>
                            <form method="POST" action="{{ route('clients.contacts.destroy', [$client, $contact]) }}" data-confirm="{{ __('table.confirm_delete') }}" data-confirm-danger>
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-icon btn-icon-danger" aria-label="{{ __('clients.remove') }}"><x-icon name="trash" /></button>
                            </form>
                        </div>
                    @endcan
                </li>
                @can('clients.update')
                    <li class="collapse contact-edit" id="contact-edit-{{ $contact->id }}">
                        <form method="POST" action="{{ route('clients.contacts.update', [$client, $contact]) }}" class="form-grid form-grid-4">
                            @csrf
                            @method('PUT')
                            @include('clients.partials.contact-inputs', ['contact' => $contact])
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('app.save') }}</button>
                        </form>
                    </li>
                @endcan
            @endforeach
        </ul>
    @endif

    @can('clients.update')
        <form method="POST" action="{{ route('clients.contacts.store', $client) }}" class="contact-add">
            @csrf
            <h3 class="sub-title">{{ __('clients.add_contact') }}</h3>
            <div class="form-grid form-grid-4">
                @include('clients.partials.contact-inputs', ['contact' => null])
            </div>
            <button type="submit" class="btn btn-outline btn-sm">{{ __('clients.add_contact') }}</button>
        </form>
    @endcan
</x-panel>
