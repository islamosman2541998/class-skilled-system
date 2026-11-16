{{-- Open purchase invoices of the supplier with an amount box each (spec §8.16). Expects $openPurchases, $allocations (purchase id => amount). --}}
@if ($openPurchases->isEmpty())
    <p class="text-muted mb-0">{{ __('resources.vouchers.no_open_purchases') }}</p>
@else
    <div class="table-responsive">
        <table class="table q-view mb-0">
            <thead>
                <tr>
                    <th scope="col">{{ __('resources.purchases.purchase') }}</th>
                    <th scope="col">{{ __('resources.purchases.supplier_invoice_no') }}</th>
                    <th scope="col">{{ __('resources.purchases.project') }}</th>
                    <th scope="col" class="num">{{ __('resources.purchases.remaining') }}</th>
                    <th scope="col" class="num">{{ __('resources.vouchers.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($openPurchases as $openPurchase)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('purchases.show', $openPurchase) }}" target="_blank" rel="noopener"><bdi dir="ltr">{{ $openPurchase->number }}</bdi></a></td>
                        <td class="text-nowrap"><bdi dir="ltr">{{ $openPurchase->supplier_invoice_no }}</bdi></td>
                        <td class="text-nowrap"><bdi dir="ltr">{{ $openPurchase->project->number }}</bdi></td>
                        <td class="num text-nowrap"><bdi dir="ltr">{{ money($openPurchase->remaining()) }}</bdi></td>
                        <td class="num" style="min-width: 9rem;">
                            <input name="allocations[{{ $openPurchase->id }}]" value="{{ $allocations[$openPurchase->id] ?? '' }}" class="form-control form-control-sm text-end" dir="ltr" inputmode="decimal" aria-label="{{ __('resources.vouchers.amount') }} {{ $openPurchase->number }}" data-allocation>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@error('allocations')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
