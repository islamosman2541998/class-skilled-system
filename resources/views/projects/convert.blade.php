@extends('layouts.app')

@section('title', __('projects.convert'))

@section('content')
    <x-page-header :title="__('projects.convert')" :breadcrumbs="[__('quotations.quotations') => route('quotations.index'), $quotation->number => route('quotations.show', $quotation)]" />

    <form method="POST" action="{{ route('quotations.convert', $quotation) }}" data-dirty-check novalidate>
        @csrf

        <x-panel :title="__('projects.from_quotation')">
            <dl class="detail-list">
                <div><dt>{{ __('projects.title') }}</dt><dd>{{ $quotation->title }}</dd></div>
                <div><dt>{{ __('projects.client') }}</dt><dd>{{ $quotation->client->name }}</dd></div>
                @if ($quotation->service)
                    <div><dt>{{ __('projects.service') }}</dt><dd>{{ $quotation->service->name }}</dd></div>
                @endif
                <div><dt>{{ __('projects.contract_value') }}</dt><dd><bdi dir="ltr">{{ money($quotation->total) }}</bdi></dd></div>
                <div><dt>{{ __('quotations.items') }}</dt><dd><bdi dir="ltr">{{ $quotation->items->count() }}</bdi></dd></div>
                <div><dt>{{ __('quotations.payments') }}</dt><dd>{{ $quotation->payments->map(fn ($p) => $p->label(app()->getLocale()).' '.rtrim(rtrim($p->percent, '0'), '.').'%')->implode(__('app.comma')) }}</dd></div>
            </dl>
            <p class="form-text mb-0 mt-3">{{ __('projects.convert_hint') }}</p>
        </x-panel>

        <x-panel :title="__('projects.schedule')">
            <div class="form-grid">
                <x-field name="billing_method" :label="__('projects.billing_method')" required>
                    <select id="billing_method" name="billing_method" class="form-select">
                        @foreach ($billingMethods as $method)
                            <option value="{{ $method->value }}" @selected(old('billing_method', 'installments') === $method->value)>{{ $method->label() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="start_date" :label="__('projects.start_date')">
                    <input id="start_date" name="start_date" value="{{ old('start_date', today()->format('Y-m-d')) }}" class="form-control" data-date dir="ltr">
                </x-field>
                <x-field name="planned_end_date" :label="__('projects.planned_end_date')">
                    <input id="planned_end_date" name="planned_end_date" value="{{ old('planned_end_date') }}" @class(['form-control', 'is-invalid' => $errors->has('planned_end_date')]) data-date dir="ltr">
                </x-field>
                <x-field name="manager_id" :label="__('projects.manager')" required>
                    <select id="manager_id" name="manager_id" class="form-select" data-select required>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) old('manager_id', auth()->id()) === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="members" :label="__('projects.members')" for="members">
                    <select id="members" name="members[]" class="form-select" data-select multiple>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(in_array($user->id, array_map('intval', (array) old('members', [])), true))>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
            <x-field name="description" :label="__('projects.description')">
                <textarea id="description" name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
            </x-field>
        </x-panel>

        <x-sticky-actions>
            <button type="submit" class="btn btn-primary">{{ __('projects.convert') }}</button>
            <a href="{{ route('quotations.show', $quotation) }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </x-sticky-actions>
    </form>
@endsection
