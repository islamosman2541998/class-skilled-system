{{-- Workflow buttons of a quotation (spec §8.5), shown by state and permission. --}}
@php
    $open = ! $quotation->isLocked();
    $reasons = \App\Models\Lookup::options(\App\Enums\LookupType::RejectionReason);
@endphp

@foreach (\App\Http\Controllers\QuotationController::$primaryActionViews as $view)
    @include($view, ['quotation' => $quotation])
@endforeach

@if ($open && $quotation->status === \App\Enums\QuotationStatus::Draft)
    @can('quotations.update')
        <form method="POST" action="{{ route('quotations.send', $quotation) }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-outline"><x-icon name="send" :size="16" /> {{ __('quotations.send') }}</button>
        </form>
    @endcan
@endif

@if ($open)
    @can('quotations.approve')
        <form method="POST" action="{{ route('quotations.accept', $quotation) }}" data-confirm="{{ __('quotations.accept_confirm') }}">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-primary"><x-icon name="check" :size="16" /> {{ __('quotations.accept') }}</button>
        </form>
    @endcan
@endif

<div class="dropdown">
    <button type="button" class="btn btn-outline" data-bs-toggle="dropdown" aria-expanded="false">{{ __('quotations.more') }} <x-icon name="chevron-down" :size="14" /></button>
    <ul class="dropdown-menu dropdown-menu-end">
        @foreach (\App\Http\Controllers\QuotationController::$menuActionViews as $view)
            @include($view, ['quotation' => $quotation])
        @endforeach
        @if ($open)
            @can('quotations.update')
                <li>
                    <form method="POST" action="{{ route('quotations.revise', $quotation) }}" data-confirm="{{ __('quotations.revise_confirm') }}">
                        @csrf
                        <button type="submit" class="dropdown-item">{{ __('quotations.revise') }}</button>
                    </form>
                </li>
            @endcan
        @endif
        @can('quotations.create')
            <li>
                <form method="POST" action="{{ route('quotations.copy', $quotation) }}">
                    @csrf
                    <button type="submit" class="dropdown-item">{{ __('quotations.copy') }}</button>
                </form>
            </li>
        @endcan
        @if ($open)
            @can('quotations.update')
                <li><button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#quotation-reject">{{ __('quotations.reject') }}</button></li>
            @endcan
        @endif
    </ul>
</div>

@if ($open)
    @can('quotations.update')
        @push('modals')
            <div class="modal fade" id="quotation-reject" tabindex="-1" aria-labelledby="quotation-reject-title" aria-hidden="true">
                <div class="modal-dialog">
                    <form class="modal-content" method="POST" action="{{ route('quotations.reject', $quotation) }}">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header">
                            <h2 class="modal-title" id="quotation-reject-title">{{ __('quotations.reject') }}</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                        </div>
                        <div class="modal-body">
                            <x-field name="rejection_reason_id" :label="__('quotations.rejection_reason')" for="q-reject-reason" required>
                                <select id="q-reject-reason" name="rejection_reason_id" class="form-select" required>
                                    <option value=""></option>
                                    @foreach ($reasons as $reason)
                                        <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                    @endforeach
                                </select>
                            </x-field>
                            <x-field name="rejection_note" :label="__('quotations.rejection_note')" for="q-reject-note">
                                <textarea id="q-reject-note" name="rejection_note" class="form-control" rows="2" maxlength="2000"></textarea>
                            </x-field>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                            <button type="submit" class="btn btn-danger">{{ __('quotations.reject') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        @endpush
    @endcan
@endif
