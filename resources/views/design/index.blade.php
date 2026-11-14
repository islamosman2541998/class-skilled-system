@extends('layouts.app')

@section('title', __('design.title'))

@section('content')
    <x-page-header :title="__('design.title')" :breadcrumbs="[__('settings.settings') => route('settings.index')]">
        <x-slot:actions>
            <a href="{{ route('design.record') }}" class="btn btn-outline">{{ __('design.record_title') }}</a>
        </x-slot:actions>
    </x-page-header>

    <div class="row g-3">
        <div class="col-lg-6">
            <x-panel :title="__('design.buttons')">
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-primary">{{ __('design.primary') }}</button>
                    <button type="button" class="btn btn-outline">{{ __('design.secondary') }}</button>
                    <button type="button" class="btn btn-danger">{{ __('design.danger') }}</button>
                    <button type="button" class="btn btn-outline btn-sm"><x-icon name="download" :size="16" /> {{ __('design.small') }}</button>
                    <button type="button" class="btn btn-primary" disabled>{{ __('design.disabled') }}</button>
                    <button type="button" class="btn-icon" aria-label="{{ __('design.primary') }}"><x-icon name="pencil" /></button>
                </div>
            </x-panel>

            <x-panel :title="__('design.statuses')">
                <div class="d-flex flex-wrap gap-2">
                    <x-status-badge color="neutral">{{ __('design.status_draft') }}</x-status-badge>
                    <x-status-badge color="info">{{ __('design.status_sent') }}</x-status-badge>
                    <x-status-badge color="success">{{ __('design.status_accepted') }}</x-status-badge>
                    <x-status-badge color="danger">{{ __('design.status_rejected') }}</x-status-badge>
                    <x-status-badge color="warning">{{ __('design.status_expired') }}</x-status-badge>
                </div>
            </x-panel>

            <x-panel :title="__('design.feedback')">
                <div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline" data-demo-toast="success" data-message="{{ __('design.sample_saved') }}">{{ __('design.toast_success') }}</button>
                    <button type="button" class="btn btn-outline" data-demo-toast="error" data-message="{{ __('design.sample_error') }}">{{ __('design.toast_error') }}</button>
                    <button type="button" class="btn btn-outline" data-demo-confirm data-title="{{ __('design.confirm_question') }}">{{ __('design.confirm') }}</button>
                    <button type="button" class="btn btn-outline" data-demo-confirm data-title="{{ __('design.confirm_all') }}" data-require="37">{{ __('design.confirm_typed') }}</button>
                </div>
            </x-panel>
        </div>

        <div class="col-lg-6">
            <x-panel :title="__('design.forms')">
                <div class="form-grid">
                    <x-field name="demo_client" :label="__('design.client')" required>
                        <input id="demo_client" class="form-control" value="{{ __('design.client_value') }}">
                    </x-field>
                    <x-field name="demo_phone" :label="__('app.phone')">
                        <input id="demo_phone" class="form-control" value="{{ __('design.phone_value') }}" dir="ltr" inputmode="tel">
                    </x-field>
                    <x-field name="demo_service" :label="__('design.service')" required>
                        <select id="demo_service" class="form-select" data-select>
                            @foreach ($services as $service)
                                <option value="{{ $service->id }}">{{ $service->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field name="demo_date" :label="__('design.issue_date')">
                        <input id="demo_date" class="form-control" data-date value="{{ now()->format('Y-m-d') }}" dir="ltr">
                    </x-field>
                    <x-field name="demo_period" :label="__('design.period')">
                        <input id="demo_period" class="form-control" data-date-range dir="ltr">
                    </x-field>
                    <x-field name="demo_amount" :label="__('design.amount')" :hint="__('design.vat_included')">
                        <input id="demo_amount" class="form-control text-end" value="{{ money('48250', false) }}" dir="ltr" inputmode="decimal">
                    </x-field>
                    <x-field name="demo_amount_error" :label="__('design.amount')">
                        <input id="demo_amount_error" class="form-control is-invalid" value="-5" dir="ltr">
                    </x-field>
                </div>
                <x-field name="demo_notes" :label="__('design.notes')">
                    <textarea id="demo_notes" class="form-control" rows="3">{{ __('design.notes_value') }}</textarea>
                </x-field>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="demo_switch" checked>
                    <label class="form-check-label" for="demo_switch">{{ __('users.active') }}</label>
                </div>
            </x-panel>
        </div>

        <div class="col-12">
            <h2 class="section-title">{{ __('design.table') }}</h2>
            <livewire:tables.lookups-table type="service" />
        </div>

        <div class="col-lg-8">
            <x-attachments :model="$user" :action="route('users.attachments.store', $user)" />
        </div>

        <div class="col-lg-4">
            <x-panel :title="__('design.empty')">
                <x-empty-state :message="__('design.empty_message')">
                    <button type="button" class="btn btn-primary btn-sm"><x-icon name="plus" :size="16" /> {{ __('design.empty_action') }}</button>
                </x-empty-state>
            </x-panel>
        </div>
    </div>

    @push('scripts')
        <script type="module">
            document.querySelectorAll('[data-demo-toast]').forEach((button) => button.addEventListener('click', () => window.toast(button.dataset.demoToast, button.dataset.message)));
            document.querySelectorAll('[data-demo-confirm]').forEach((button) => button.addEventListener('click', () => window.confirmAction({
                title: button.dataset.title,
                danger: true,
                requireText: button.dataset.require || null,
            })));
        </script>
    @endpush
@endsection
