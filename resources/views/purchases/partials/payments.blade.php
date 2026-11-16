{{-- Payment vouchers that pay this purchase invoice (spec §8.16). --}}
@can('payment_vouchers.view')
    @php($allocations = \App\Models\PaymentAllocation::query()->where('purchase_id', $purchase->id)->with('voucher')->get())
    <x-panel :title="__('resources.vouchers.vouchers')" class="panel-flush">
        @if (bccomp($purchase->remaining(), '0', 2) > 0)
            @can('payment_vouchers.create')
                <x-slot:tools>
                    <a href="{{ route('payment-vouchers.create', ['purchase' => $purchase->id]) }}" class="btn btn-outline btn-sm"><x-icon name="plus" :size="16" /> {{ __('resources.vouchers.new') }}</a>
                </x-slot:tools>
            @endcan
        @endif
        @if ($allocations->isEmpty())
            <x-empty-state :message="__('resources.vouchers.no_payments_yet')" icon="cash-banknote" />
        @else
            <div class="table-responsive">
                <table class="table q-view mb-0">
                    <tbody>
                        @foreach ($allocations as $allocation)
                            <tr>
                                <td><a href="{{ route('payment-vouchers.show', $allocation->voucher) }}"><bdi dir="ltr">{{ $allocation->voucher->number }}</bdi></a></td>
                                <td><bdi dir="ltr">{{ fmt_date($allocation->voucher->voucher_date) }}</bdi></td>
                                <td><x-status-badge :color="$allocation->voucher->status->color()">{{ $allocation->voucher->status->label() }}</x-status-badge></td>
                                <td class="num"><bdi dir="ltr">{{ money($allocation->amount) }}</bdi></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-panel>
@endcan
