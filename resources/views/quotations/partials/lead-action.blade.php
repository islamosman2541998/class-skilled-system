@can('quotations.create')
    @unless ($lead->isClosed())
        <a href="{{ route('quotations.create', ['lead' => $lead->id]) }}" class="btn btn-outline"><x-icon name="file-invoice" :size="16" /> {{ __('quotations.new_quotation') }}</a>
    @endunless
@endcan
