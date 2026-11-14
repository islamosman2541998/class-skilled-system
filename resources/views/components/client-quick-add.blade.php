{{--
    Add a client without leaving the current form (spec §5.3). On success the page receives a
    `client-created` event with {id, name}; resources/js/clients.js adds it to [data-client-select].
--}}
@can('clients.create')
    <button type="button" class="btn btn-outline btn-sm" data-bs-toggle="modal" data-bs-target="#client-quick-add">
        <x-icon name="plus" :size="16" /> {{ __('clients.quick_add') }}
    </button>

    @pushOnce('modals')
        <div class="modal fade" id="client-quick-add" tabindex="-1" aria-labelledby="client-quick-add-title" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <form class="modal-content" method="POST" action="{{ route('clients.quick-store') }}" novalidate data-client-quick-form>
                    @csrf
                    <div class="modal-header">
                        <h2 class="modal-title" id="client-quick-add-title">{{ __('clients.new_client') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                    </div>
                    <div class="modal-body">
                        @include('clients.partials.fields', ['client' => new \App\Models\Client(['type' => \App\Enums\ClientType::Individual]), 'prefix' => 'qa-'])
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endPushOnce

    @pushOnce('scripts')
        @vite('resources/js/clients.js')
    @endPushOnce
@endcan
