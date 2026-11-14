@extends('layouts.app')

@php
    $editing = $quotation->exists;
    $title = $editing ? __('quotations.edit_quotation').' '.$quotation->number : __('quotations.new_quotation');
    $value = fn (string $field) => old($field, $quotation->{$field} instanceof \BackedEnum ? $quotation->{$field}->value : $quotation->{$field});
    $vatRate = $editing ? $quotation->vat_rate : $finance->vat_rate;
@endphp

@section('title', $title)

@section('content')
    <x-page-header :title="$title" :breadcrumbs="[__('quotations.quotations') => route('quotations.index')] + ($editing ? [$quotation->number => route('quotations.show', $quotation)] : [])" />

    <form method="POST" action="{{ $editing ? route('quotations.update', $quotation) : route('quotations.store') }}" data-dirty-check novalidate
        data-quotation-form data-vat="{{ $vatRate }}" data-decimals="{{ $finance->decimals }}"
        data-currency="{{ app()->getLocale() === 'ar' ? $finance->currency_symbol_ar : $finance->currency_symbol_en }}">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <x-panel :title="__('quotations.header')">
            <x-slot:tools>
                @unless ($editing || $lead)
                    <x-client-quick-add />
                @endunless
            </x-slot:tools>
            @if ($lead)
                <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                <p class="mb-3">{{ __('quotations.lead') }}: <a href="{{ route('leads.show', $lead) }}"><bdi dir="ltr">{{ $lead->number }}</bdi></a> · {{ $lead->displayName() }}</p>
            @endif
            <div class="form-grid">
                @if (! $editing && (! $lead || $lead->client_id))
                    <x-field name="client_id" :label="__('quotations.client')" :required="! $lead">
                        <select id="client_id" name="client_id" data-select data-client-select data-remote="{{ route('clients.options') }}">
                            <option value=""></option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" selected>{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </x-field>
                @elseif ($editing)
                    <x-field name="client_display" :label="__('quotations.client')">
                        <input id="client_display" class="form-control" value="{{ $quotation->client->name }}" disabled>
                    </x-field>
                @endif
                <x-field name="title_ar" :label="__('quotations.title_ar')" required>
                    <input id="title_ar" name="title_ar" value="{{ $value('title_ar') }}" @class(['form-control', 'is-invalid' => $errors->has('title_ar')]) required maxlength="190" dir="rtl">
                </x-field>
                <x-field name="title_en" :label="__('quotations.title_en')">
                    <input id="title_en" name="title_en" value="{{ $value('title_en') }}" class="form-control" maxlength="190" dir="ltr">
                </x-field>
                <x-field name="service_id" :label="__('quotations.service')">
                    <select id="service_id" name="service_id" class="form-select" data-service-select>
                        <option value=""></option>
                        @foreach ($services as $service)
                            <option value="{{ $service->id }}" @selected((int) $value('service_id') === $service->id)>{{ $service->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="emirate_id" :label="__('quotations.emirate')">
                    <select id="emirate_id" name="emirate_id" class="form-select">
                        <option value=""></option>
                        @foreach ($emirates as $emirate)
                            <option value="{{ $emirate->id }}" @selected((int) $value('emirate_id') === $emirate->id)>{{ $emirate->name }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field name="location" :label="__('quotations.location')">
                    <input id="location" name="location" value="{{ $value('location') }}" class="form-control" maxlength="190">
                </x-field>
                <x-field name="issue_date" :label="__('quotations.issue_date')" required>
                    <input id="issue_date" name="issue_date" value="{{ old('issue_date', $quotation->issue_date?->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('issue_date')]) data-date dir="ltr" required>
                </x-field>
                <x-field name="valid_until" :label="__('quotations.valid_until')" required>
                    <input id="valid_until" name="valid_until" value="{{ old('valid_until', $quotation->valid_until?->format('Y-m-d')) }}" @class(['form-control', 'is-invalid' => $errors->has('valid_until')]) data-date dir="ltr" required>
                </x-field>
                <x-field name="assigned_to" :label="__('quotations.assigned_to')" required>
                    <select id="assigned_to" name="assigned_to" class="form-select" data-select required>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((int) $value('assigned_to') === $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                </x-field>
            </div>
        </x-panel>

        <x-panel :title="__('quotations.items')">
            <x-slot:tools>
                <div class="q-picker">
                    <select data-select data-price-picker data-remote="{{ route('price-items.search') }}" data-placeholder="{{ __('quotations.add_from_price_list') }}" aria-label="{{ __('quotations.add_from_price_list') }}">
                        <option value=""></option>
                    </select>
                </div>
            </x-slot:tools>
            <div class="q-items-head" aria-hidden="true">
                <span></span>
                <span>{{ __('quotations.description') }}</span>
                <span>{{ __('quotations.unit') }}</span>
                <span>{{ __('quotations.quantity') }}</span>
                <span>{{ __('quotations.unit_price') }}</span>
                <span>{{ __('quotations.line_total') }}</span>
                <span></span>
            </div>
            <div class="q-items" data-items>
                @foreach ($items as $i => $item)
                    @include('quotations.partials.item-row', ['i' => $i, 'item' => $item])
                @endforeach
            </div>
            <template data-item-template>
                @include('quotations.partials.item-row', ['i' => '__I__', 'item' => []])
            </template>
            <div class="invalid-feedback d-block" data-error-for="items">@error('items'){{ $message }}@enderror</div>
            <p class="form-text">{{ __('quotations.section_hint') }}</p>
            <button type="button" class="btn btn-outline btn-sm" data-item-add><x-icon name="plus" :size="16" /> {{ __('quotations.add_item') }}</button>

            <div class="q-totals">
                <div class="form-grid q-discount">
                    <x-field name="discount_type" :label="__('quotations.discount_type')">
                        <select id="discount_type" name="discount_type" class="form-select" data-calc>
                            <option value="">{{ __('quotations.no_discount') }}</option>
                            @foreach (\App\Enums\DiscountType::cases() as $type)
                                <option value="{{ $type->value }}" @selected($value('discount_type') === $type->value)>{{ $type->label() }}</option>
                            @endforeach
                        </select>
                    </x-field>
                    <x-field name="discount_value" :label="__('quotations.discount_value')">
                        <input id="discount_value" name="discount_value" value="{{ $value('discount_value') ?? '0' }}" @class(['form-control text-end', 'is-invalid' => $errors->has('discount_value')]) dir="ltr" inputmode="decimal" data-calc>
                    </x-field>
                </div>
                <dl class="q-summary">
                    <div><dt>{{ __('quotations.subtotal') }}</dt><dd data-total="subtotal"></dd></div>
                    <div><dt>{{ __('quotations.discount') }}</dt><dd data-total="discount"></dd></div>
                    <div><dt>{{ __('quotations.taxable') }}</dt><dd data-total="taxable"></dd></div>
                    <div><dt>{{ __('quotations.vat', ['rate' => rtrim(rtrim($vatRate, '0'), '.')]) }}</dt><dd data-total="vat"></dd></div>
                    <div class="q-grand"><dt>{{ __('quotations.total') }}</dt><dd data-total="total"></dd></div>
                </dl>
            </div>
        </x-panel>

        <x-panel :title="__('quotations.payments')">
            <x-slot:tools>
                <div class="d-flex gap-2 align-items-center">
                    <select class="form-select form-select-sm" data-payment-template aria-label="{{ __('quotations.payment_template') }}">
                        @foreach ($paymentTemplates as $index => $template)
                            <option value="{{ $index }}">{{ app()->getLocale() === 'en' && $template['name_en'] ? $template['name_en'] : $template['name_ar'] }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-outline btn-sm" data-payment-apply>{{ __('quotations.apply_template') }}</button>
                </div>
            </x-slot:tools>
            <script type="application/json" data-payment-templates>@json($paymentTemplates)</script>
            <div data-payments>
                @foreach ($payments as $p => $payment)
                    @include('quotations.partials.payment-row', ['p' => $p, 'payment' => $payment])
                @endforeach
            </div>
            <template data-payment-template-row>
                @include('quotations.partials.payment-row', ['p' => '__P__', 'payment' => []])
            </template>
            <div class="invalid-feedback d-block" data-error-for="payments">@error('payments'){{ $message }}@enderror</div>
            <div class="payment-template-footer">
                <button type="button" class="btn btn-outline btn-sm" data-payment-add><x-icon name="plus" :size="16" /> {{ __('quotations.add_payment') }}</button>
                <span class="ms-auto">{{ __('quotations.payments_sum') }}: <bdi dir="ltr"><span data-payment-sum></span>%</bdi></span>
            </div>
        </x-panel>

        <x-panel :title="__('quotations.terms')">
            <div class="form-grid">
                <x-field name="terms_ar" :label="__('quotations.terms_ar')">
                    <textarea id="terms_ar" name="terms_ar" class="form-control" rows="5" dir="rtl">{{ $value('terms_ar') }}</textarea>
                </x-field>
                <x-field name="terms_en" :label="__('quotations.terms_en')">
                    <textarea id="terms_en" name="terms_en" class="form-control" rows="5" dir="ltr">{{ $value('terms_en') }}</textarea>
                </x-field>
            </div>
            <x-field name="notes" :label="__('quotations.notes')">
                <textarea id="notes" name="notes" class="form-control" rows="2">{{ $value('notes') }}</textarea>
            </x-field>
        </x-panel>

        <x-sticky-actions>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ $editing ? route('quotations.show', $quotation) : route('quotations.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
            <span class="ms-auto fw-semibold">{{ __('quotations.total') }}: <bdi dir="ltr" data-total="total"></bdi></span>
        </x-sticky-actions>
    </form>

    @pushOnce('scripts')
        @vite('resources/js/quotation-form.js')
    @endPushOnce
@endsection
