{{-- Open invoices of the client with an amount box each (spec §8.11). Expects $openInvoices, $allocations (invoice id => amount). --}}
@if ($openInvoices->isEmpty())
    <p class="text-muted mb-0">{{ __('finance.receipts.no_open_invoices') }}</p>
@else
    <div class="table-responsive">
        <table class="table q-view mb-0">
            <thead>
                <tr>
                    <th scope="col">{{ __('finance.invoices.invoice') }}</th>
                    <th scope="col">{{ __('finance.project') }}</th>
                    <th scope="col">{{ __('finance.invoices.due_date') }}</th>
                    <th scope="col" class="num">{{ __('finance.invoices.remaining') }}</th>
                    <th scope="col" class="num">{{ __('finance.receipts.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($openInvoices as $openInvoice)
                    <tr>
                        <td class="text-nowrap"><a href="{{ route('invoices.show', $openInvoice) }}" target="_blank" rel="noopener"><bdi dir="ltr">{{ $openInvoice->number }}</bdi></a></td>
                        <td class="text-nowrap"><bdi dir="ltr">{{ $openInvoice->project->number }}</bdi></td>
                        <td class="text-nowrap"><bdi dir="ltr" @class(['text-danger' => $openInvoice->isOverdue()])>{{ fmt_date($openInvoice->due_date) }}</bdi></td>
                        <td class="num text-nowrap"><bdi dir="ltr">{{ money($openInvoice->remaining()) }}</bdi></td>
                        <td class="num" style="min-width: 9rem;">
                            <input name="allocations[{{ $openInvoice->id }}]" value="{{ $allocations[$openInvoice->id] ?? '' }}" class="form-control form-control-sm text-end" dir="ltr" inputmode="decimal" aria-label="{{ __('finance.receipts.amount') }} {{ $openInvoice->number }}" data-allocation>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@error('allocations')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
