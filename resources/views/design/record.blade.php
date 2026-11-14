@extends('layouts.app')

@section('title', __('design.record_title'))

@section('content')
    <nav aria-label="{{ __('app.breadcrumb') }}">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('design.index') }}">{{ __('design.title') }}</a></li>
            <li class="breadcrumb-item"><bdi dir="ltr">{{ __('design.project_number') }}</bdi></li>
        </ol>
    </nav>

    <x-record-header :title="__('design.project_title')" :status="__('design.project_status')" status-color="info" :subtitle="__('design.client_value')">
        <x-slot:actions>
            <button type="button" class="btn btn-outline">{{ __('design.print') }}</button>
            <button type="button" class="btn btn-primary">{{ __('design.primary') }}</button>
        </x-slot:actions>
        <x-slot:figures>
            @foreach (['contract_value' => '385000', 'invoiced' => '154000', 'collected' => '115500', 'outstanding' => '38500', 'cost' => '201340.75', 'profit' => '183659.25'] as $label => $value)
                <div class="figure">
                    <span class="figure-label">{{ __('design.'.$label) }}</span>
                    <span class="figure-value"><bdi dir="ltr">{{ money($value) }}</bdi></span>
                </div>
            @endforeach
        </x-slot:figures>
    </x-record-header>

    <x-tabs :tabs="$tabs" :active="$active" />

    @if ($active === 'attachments')
        <x-attachments :model="$user" :action="route('users.attachments.store', $user)" />
    @elseif ($active === 'activity')
        <x-activity-tab :subject="$user" />
    @else
        <form data-dirty-check novalidate onsubmit="return false">
            <x-panel :title="__('design.section_main')">
                <div class="form-grid">
                    <x-field name="title_ar" :label="__('design.title_ar')" required>
                        <input id="title_ar" class="form-control" value="{{ __('design.project_title') }}" dir="rtl">
                    </x-field>
                    <x-field name="title_en" :label="__('design.title_en')" :hint="__('settings.name_en_hint')">
                        <input id="title_en" class="form-control" dir="ltr">
                    </x-field>
                    <x-field name="emirate" :label="__('design.emirate')" required>
                        <select id="emirate" class="form-select" data-select>
                            @foreach ($emirates as $emirate)
                                <option value="{{ $emirate->id }}">{{ $emirate->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field name="manager" :label="__('design.manager')">
                        <select id="manager" class="form-select" data-select>
                            @foreach ($managers as $manager)
                                <option value="{{ $manager->id }}">{{ $manager->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                </div>
            </x-panel>

            <x-panel :title="__('design.section_dates')">
                <div class="form-grid">
                    <x-field name="start_date" :label="__('design.start_date')" required>
                        <input id="start_date" class="form-control" data-date dir="ltr" value="{{ now()->subMonth()->format('Y-m-d') }}">
                    </x-field>
                    <x-field name="end_date" :label="__('design.end_date')">
                        <input id="end_date" class="form-control" data-date dir="ltr" value="{{ now()->addMonths(2)->format('Y-m-d') }}">
                    </x-field>
                    <x-field name="contract" :label="__('design.contract_value')" required>
                        <input id="contract" class="form-control text-end" dir="ltr" value="{{ money('385000', false) }}">
                    </x-field>
                </div>
            </x-panel>

            <x-panel :title="__('design.section_details')">
                <x-field name="description" :label="__('design.description')">
                    <textarea id="description" class="form-control" rows="6">{{ __('design.notes_value') }}</textarea>
                </x-field>
            </x-panel>

            <x-sticky-actions>
                <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
                <a href="{{ route('design.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
            </x-sticky-actions>
        </form>
    @endif
@endsection
