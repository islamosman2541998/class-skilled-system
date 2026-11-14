{{-- Global search (spec §5.6): the field opens the modal; Ctrl+K does the same from anywhere. --}}
<button type="button" class="search-trigger" data-search-open aria-haspopup="dialog" aria-controls="search-modal">
    <x-icon name="search" :size="16" />
    <span class="search-trigger-text">{{ __('app.search_placeholder') }}</span>
    <kbd class="search-trigger-key" dir="ltr">{{ __('app.search_shortcut') }}</kbd>
</button>

@push('modals')
    <div class="modal" id="search-modal" tabindex="-1" aria-labelledby="search-modal-label" aria-hidden="true" data-search-url="{{ route('search') }}">
        <div class="modal-dialog modal-lg search-dialog">
            <div class="modal-content">
                <div class="search-box">
                    <x-icon name="search" :size="18" />
                    <label for="search-input" class="visually-hidden" id="search-modal-label">{{ __('app.search') }}</label>
                    <input id="search-input" type="search" class="search-input" placeholder="{{ __('app.search_placeholder') }}" autocomplete="off" role="combobox" aria-expanded="false" aria-controls="search-results">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.close') }}"></button>
                </div>
                <div class="search-results" id="search-results" role="listbox" data-search-results>
                    <p class="search-empty">{{ __('app.search_hint') }}</p>
                </div>
            </div>
        </div>
    </div>
@endpush
