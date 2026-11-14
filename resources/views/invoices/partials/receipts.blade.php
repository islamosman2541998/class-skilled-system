{{-- Receipts paying this invoice, and "record receipt" while something remains (spec §8.10, §8.11). --}}
@can('receipts.view')
    @php
        $payments = $invoice->allocations()->with('receipt')->get();
    @endphp
    @if (! $invoice->isDraft())
        <x-panel :title="__('finance.receipts.payments_title')">
            @if ($invoice->status->isActive() && bccomp($invoice->remaining(), '0', 2) > 0)
                @can('receipts.create')
                    <x-slot:tools>
                        <a href="{{ route('receipts.create', ['invoice' => $invoice->id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('finance.receipts.record') }}</a>
                    </x-slot:tools>
                @endcan
            @endif

            @forelse ($payments as $payment)
                <div @class(['d-flex flex-wrap align-items-center gap-2 py-2', 'border-top' => ! $loop->first])>
                    @include('receipts.partials.number', ['row' => $payment->receipt])
                    <span class="text-muted"><bdi dir="ltr">{{ fmt_date($payment->receipt->receipt_date) }}</bdi></span>
                    <span class="ms-auto"><bdi dir="ltr">{{ money($payment->amount) }}</bdi></span>
                    @unless ($payment->receipt->isActive())
                        @include('receipts.partials.status', ['row' => $payment->receipt])
                    @endunless
                </div>
            @empty
                <p class="text-muted mb-0">{{ __('finance.receipts.no_payments') }}</p>
            @endforelse
        </x-panel>
    @endif
@endcan
