{{-- Approve a submitted claim; it opens its draft invoice (spec §8.14). An approved claim links to that invoice. --}}
@if ($claim->status === \App\Enums\ClaimStatus::Submitted)
    @can('claims.approve')
        <form method="POST" action="{{ route('claims.approve', $claim) }}" data-confirm="{{ __('site.claims.approve_confirm') }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-primary"><x-icon name="check" :size="16" /> {{ __('site.claims.approve') }}</button>
        </form>
    @endcan
@elseif ($claim->invoice)
    @can('invoices.view')
        <a href="{{ route('invoices.show', $claim->invoice) }}" class="btn btn-outline"><x-icon name="file-invoice" :size="16" /> {{ $claim->invoice->number ?? __('site.claims.draft_invoice') }}</a>
    @endcan
@endif
