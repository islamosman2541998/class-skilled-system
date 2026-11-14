{{-- Print in a chosen language and send by WhatsApp (spec §5.10). --}}
@can('receipts.print')
        <div class="dropdown">
            <button type="button" class="btn btn-outline" data-bs-toggle="dropdown" aria-expanded="false"><x-icon name="printer" :size="16" /> {{ __('quotations.print') }} <x-icon name="chevron-down" :size="14" /></button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach (['ar', 'en', 'both'] as $lang)
                    <li><a class="dropdown-item" href="{{ route('receipts.pdf', [$receipt, 'lang' => $lang]) }}" target="_blank" rel="noopener">{{ __('quotations.print_'.$lang) }}</a></li>
                @endforeach
            </ul>
        </div>
        <div class="dropdown">
            <button type="button" class="btn btn-outline" data-bs-toggle="dropdown" aria-expanded="false"><x-icon name="brand-whatsapp" :size="16" /> {{ __('quotations.send_whatsapp') }} <x-icon name="chevron-down" :size="14" /></button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach (['ar', 'en', 'both'] as $lang)
                    <li><button type="button" class="dropdown-item" data-share-url="{{ route('receipts.share', $receipt) }}" data-lang="{{ $lang }}">{{ __('quotations.print_'.$lang) }}</button></li>
                @endforeach
            </ul>
        </div>

        @pushOnce('scripts')
            <script type="module">
                document.addEventListener('click', async (event) => {
                    const button = event.target.closest('[data-share-url]');
                    if (!button) return;

                    // Open the tab first so the browser does not block it as a pop-up.
                    const tab = window.open('', '_blank', 'noopener');
                    const response = await fetch(button.dataset.shareUrl, {
                        method: 'POST',
                        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, Accept: 'application/json', 'Content-Type': 'application/json' },
                        body: JSON.stringify({ lang: button.dataset.lang }),
                    });

                    if (!response.ok) {
                        tab?.close();
                        window.toast('error', window.I18N.request_failed);
                        return;
                    }

                    const data = await response.json();
                    if (tab) tab.location.href = data.whatsapp; else window.location.href = data.whatsapp;
                });
            </script>
        @endPushOnce
@endcan
