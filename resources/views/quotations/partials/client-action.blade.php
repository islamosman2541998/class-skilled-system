@can('quotations.create')
    <a href="{{ route('quotations.create', ['client' => $client->id]) }}" class="btn btn-outline"><x-icon name="file-invoice" :size="16" /> {{ __('quotations.new_quotation') }}</a>
@endcan
