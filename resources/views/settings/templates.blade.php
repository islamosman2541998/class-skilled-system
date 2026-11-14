@extends('layouts.app')

@section('title', __('settings.groups.templates'))

@php
    $templates = old('payment_templates', $settings->payment_templates);
@endphp

@section('content')
    <x-page-header :title="__('settings.groups.templates')" :breadcrumbs="[__('settings.settings') => route('settings.index')]" />

    <form method="POST" action="{{ route('settings.templates.update') }}" data-dirty-check novalidate>
        @csrf
        @method('PUT')

        <x-panel :title="__('settings.templates.quotation')">
            <div class="form-grid">
                <x-field name="quotation_terms_ar" :label="__('settings.templates.terms_ar')" required>
                    <textarea id="quotation_terms_ar" name="quotation_terms_ar" @class(['form-control', 'is-invalid' => $errors->has('quotation_terms_ar')]) rows="6" dir="rtl">{{ old('quotation_terms_ar', $settings->quotation_terms_ar) }}</textarea>
                </x-field>
                <x-field name="quotation_terms_en" :label="__('settings.templates.terms_en')" required>
                    <textarea id="quotation_terms_en" name="quotation_terms_en" @class(['form-control', 'is-invalid' => $errors->has('quotation_terms_en')]) rows="6" dir="ltr">{{ old('quotation_terms_en', $settings->quotation_terms_en) }}</textarea>
                </x-field>
                <x-field name="quotation_footer_ar" :label="__('settings.templates.footer_ar')">
                    <textarea id="quotation_footer_ar" name="quotation_footer_ar" class="form-control" rows="2" dir="rtl">{{ old('quotation_footer_ar', $settings->quotation_footer_ar) }}</textarea>
                </x-field>
                <x-field name="quotation_footer_en" :label="__('settings.templates.footer_en')">
                    <textarea id="quotation_footer_en" name="quotation_footer_en" class="form-control" rows="2" dir="ltr">{{ old('quotation_footer_en', $settings->quotation_footer_en) }}</textarea>
                </x-field>
            </div>
        </x-panel>

        <x-panel :title="__('settings.templates.invoice')">
            <div class="form-grid">
                <x-field name="invoice_terms_ar" :label="__('settings.templates.terms_ar')" required>
                    <textarea id="invoice_terms_ar" name="invoice_terms_ar" @class(['form-control', 'is-invalid' => $errors->has('invoice_terms_ar')]) rows="4" dir="rtl">{{ old('invoice_terms_ar', $settings->invoice_terms_ar) }}</textarea>
                </x-field>
                <x-field name="invoice_terms_en" :label="__('settings.templates.terms_en')" required>
                    <textarea id="invoice_terms_en" name="invoice_terms_en" @class(['form-control', 'is-invalid' => $errors->has('invoice_terms_en')]) rows="4" dir="ltr">{{ old('invoice_terms_en', $settings->invoice_terms_en) }}</textarea>
                </x-field>
                <x-field name="invoice_footer_ar" :label="__('settings.templates.footer_ar')">
                    <textarea id="invoice_footer_ar" name="invoice_footer_ar" @class(['form-control', 'is-invalid' => $errors->has('invoice_footer_ar')]) rows="2" dir="rtl">{{ old('invoice_footer_ar', $settings->invoice_footer_ar) }}</textarea>
                </x-field>
                <x-field name="invoice_footer_en" :label="__('settings.templates.footer_en')">
                    <textarea id="invoice_footer_en" name="invoice_footer_en" @class(['form-control', 'is-invalid' => $errors->has('invoice_footer_en')]) rows="2" dir="ltr">{{ old('invoice_footer_en', $settings->invoice_footer_en) }}</textarea>
                </x-field>
            </div>
        </x-panel>

        <x-panel :title="__('settings.templates.whatsapp')">
            <p class="form-text mt-0">{{ __('settings.templates.whatsapp_hint') }}</p>
            <div class="form-grid">
                <x-field name="whatsapp_quotation_ar" :label="__('settings.templates.whatsapp_ar')" required>
                    <textarea id="whatsapp_quotation_ar" name="whatsapp_quotation_ar" @class(['form-control', 'is-invalid' => $errors->has('whatsapp_quotation_ar')]) rows="3" dir="rtl">{{ old('whatsapp_quotation_ar', $settings->whatsapp_quotation_ar) }}</textarea>
                </x-field>
                <x-field name="whatsapp_quotation_en" :label="__('settings.templates.whatsapp_en')" required>
                    <textarea id="whatsapp_quotation_en" name="whatsapp_quotation_en" @class(['form-control', 'is-invalid' => $errors->has('whatsapp_quotation_en')]) rows="3" dir="ltr">{{ old('whatsapp_quotation_en', $settings->whatsapp_quotation_en) }}</textarea>
                </x-field>
                <x-field name="whatsapp_invoice_ar" :label="__('settings.templates.whatsapp_invoice_ar')" required>
                    <textarea id="whatsapp_invoice_ar" name="whatsapp_invoice_ar" @class(['form-control', 'is-invalid' => $errors->has('whatsapp_invoice_ar')]) rows="3" dir="rtl">{{ old('whatsapp_invoice_ar', $settings->whatsapp_invoice_ar) }}</textarea>
                </x-field>
                <x-field name="whatsapp_invoice_en" :label="__('settings.templates.whatsapp_invoice_en')" required>
                    <textarea id="whatsapp_invoice_en" name="whatsapp_invoice_en" @class(['form-control', 'is-invalid' => $errors->has('whatsapp_invoice_en')]) rows="3" dir="ltr">{{ old('whatsapp_invoice_en', $settings->whatsapp_invoice_en) }}</textarea>
                </x-field>
                <x-field name="whatsapp_receipt_ar" :label="__('settings.templates.whatsapp_receipt_ar')" required>
                    <textarea id="whatsapp_receipt_ar" name="whatsapp_receipt_ar" @class(['form-control', 'is-invalid' => $errors->has('whatsapp_receipt_ar')]) rows="3" dir="rtl">{{ old('whatsapp_receipt_ar', $settings->whatsapp_receipt_ar) }}</textarea>
                </x-field>
                <x-field name="whatsapp_receipt_en" :label="__('settings.templates.whatsapp_receipt_en')" required>
                    <textarea id="whatsapp_receipt_en" name="whatsapp_receipt_en" @class(['form-control', 'is-invalid' => $errors->has('whatsapp_receipt_en')]) rows="3" dir="ltr">{{ old('whatsapp_receipt_en', $settings->whatsapp_receipt_en) }}</textarea>
                </x-field>
            </div>
        </x-panel>

        <x-panel :title="__('settings.templates.payments')" data-payment-templates>
            <div data-template-list>
                @foreach ($templates as $t => $template)
                    @include('settings.partials.payment-template', ['t' => $t, 'template' => $template])
                @endforeach
            </div>
            <template data-template-blank>
                @include('settings.partials.payment-template', ['t' => '__T__', 'template' => ['name_ar' => '', 'name_en' => '', 'lines' => [['label_ar' => '', 'label_en' => '', 'percent' => '']]]])
            </template>
            <template data-line-blank>
                @include('settings.partials.payment-line', ['t' => '__T__', 'l' => '__L__', 'line' => ['label_ar' => '', 'label_en' => '', 'percent' => '']])
            </template>
            <button type="button" class="btn btn-outline btn-sm" data-template-add><x-icon name="plus" :size="16" /> {{ __('settings.templates.add_template') }}</button>
        </x-panel>

        <x-sticky-actions>
            <button type="submit" class="btn btn-primary">{{ __('app.save') }}</button>
            <a href="{{ route('settings.index') }}" class="btn btn-outline">{{ __('app.cancel') }}</a>
        </x-sticky-actions>
    </form>

    @push('scripts')
        <script type="module">
            const root = document.querySelector('[data-payment-templates]');
            const list = root.querySelector('[data-template-list]');
            let nextTemplate = list.children.length;
            let nextLine = 1000;

            const updateSums = () => {
                list.querySelectorAll('[data-template]').forEach((box) => {
                    const sum = Array.from(box.querySelectorAll('[data-percent]')).reduce((total, input) => total + (parseFloat(input.value) || 0), 0);
                    const output = box.querySelector('[data-sum]');
                    output.textContent = sum.toFixed(2);
                    output.classList.toggle('text-danger', Math.abs(sum - 100) > 0.001);
                });
            };

            root.addEventListener('input', updateSums);
            root.addEventListener('click', (event) => {
                if (event.target.closest('[data-template-add]')) {
                    list.insertAdjacentHTML('beforeend', root.querySelector('template[data-template-blank]').innerHTML.replaceAll('__T__', String(nextTemplate++)).replaceAll('__L__', '0'));
                }

                const addLine = event.target.closest('[data-line-add]');
                if (addLine) {
                    const box = addLine.closest('[data-template]');
                    box.querySelector('[data-lines]').insertAdjacentHTML('beforeend', root.querySelector('template[data-line-blank]').innerHTML.replaceAll('__T__', box.dataset.template).replaceAll('__L__', String(nextLine++)));
                }

                event.target.closest('[data-line-remove]')?.closest('[data-line]')?.remove();
                event.target.closest('[data-template-remove]')?.closest('[data-template]')?.remove();
                updateSums();
            });

            updateSums();
        </script>
    @endpush
@endsection
