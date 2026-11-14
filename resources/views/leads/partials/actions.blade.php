{{-- Header actions of a lead (spec §8.2). Visit and quotation buttons appear once those modules exist. --}}
@php
    $reasons = \App\Models\Lookup::options(\App\Enums\LookupType::RejectionReason);
    $users = \App\Models\User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']);
@endphp

@foreach (\App\Http\Controllers\LeadController::$headerActionViews as $view)
    @include($view, ['lead' => $lead])
@endforeach

@can('leads.update')
    <div class="dropdown">
        <button type="button" class="btn btn-outline" data-bs-toggle="dropdown" aria-expanded="false">{{ __('leads.actions') }} <x-icon name="chevron-down" :size="14" /></button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#lead-assign">{{ __('leads.assign') }}</button></li>
            <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#lead-schedule">{{ __('leads.schedule') }}</button></li>
            @if ($lead->statusCode() === 'lost')
                <li>
                    <form method="POST" action="{{ route('leads.reopen', $lead) }}">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="dropdown-item">{{ __('leads.reopen') }}</button>
                    </form>
                </li>
            @elseif (! $lead->isClosed())
                <li><button type="button" class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#lead-close">{{ __('leads.close_lost') }}</button></li>
            @endif
        </ul>
    </div>

    @push('modals')
        <div class="modal fade" id="lead-assign" tabindex="-1" aria-labelledby="lead-assign-title" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('leads.assign', $lead) }}">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h2 class="modal-title" id="lead-assign-title">{{ __('leads.assign') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                    </div>
                    <div class="modal-body">
                        <x-field name="assigned_to" :label="__('leads.assigned_to')" for="assign-user">
                            <select id="assign-user" name="assigned_to" class="form-select">
                                <option value="">{{ __('leads.unassigned') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected($lead->assigned_to === $user->id)>{{ $user->name }}</option>
                                @endforeach
                            </select>
                        </x-field>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="lead-schedule" tabindex="-1" aria-labelledby="lead-schedule-title" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('leads.schedule', $lead) }}">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h2 class="modal-title" id="lead-schedule-title">{{ __('leads.schedule') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                    </div>
                    <div class="modal-body">
                        <x-field name="follow_up_at" :label="__('leads.follow_up_at')" for="schedule-at">
                            <input id="schedule-at" name="follow_up_at" class="form-control" data-date data-enable-time dir="ltr" value="{{ $lead->follow_up_at?->format('Y-m-d H:i') }}">
                        </x-field>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="modal fade" id="lead-close" tabindex="-1" aria-labelledby="lead-close-title" aria-hidden="true">
            <div class="modal-dialog">
                <form class="modal-content" method="POST" action="{{ route('leads.close', $lead) }}">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h2 class="modal-title" id="lead-close-title">{{ __('leads.close_lost') }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                    </div>
                    <div class="modal-body">
                        <x-field name="rejection_reason_id" :label="__('leads.rejection_reason')" for="close-reason" required>
                            <select id="close-reason" name="rejection_reason_id" class="form-select" required>
                                <option value=""></option>
                                @foreach ($reasons as $reason)
                                    <option value="{{ $reason->id }}">{{ $reason->name }}</option>
                                @endforeach
                            </select>
                        </x-field>
                        <x-field name="rejection_note" :label="__('leads.rejection_note')" for="close-note">
                            <textarea id="close-note" name="rejection_note" class="form-control" rows="2" maxlength="2000"></textarea>
                        </x-field>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                        <button type="submit" class="btn btn-danger">{{ __('leads.close_lost') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @endpush
@endcan
