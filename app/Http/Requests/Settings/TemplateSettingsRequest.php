<?php

namespace App\Http\Requests\Settings;

use Illuminate\Validation\Validator;

class TemplateSettingsRequest extends SettingsRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $whatsapp = ['required', 'string', 'max:1000', 'regex:/:link/'];

        return [
            'quotation_terms_ar' => ['required', 'string', 'max:10000'],
            'quotation_terms_en' => ['required', 'string', 'max:10000'],
            'quotation_footer_ar' => ['nullable', 'string', 'max:2000'],
            'quotation_footer_en' => ['nullable', 'string', 'max:2000'],
            'whatsapp_quotation_ar' => $whatsapp,
            'whatsapp_quotation_en' => $whatsapp,
            'invoice_terms_ar' => ['required', 'string', 'max:10000'],
            'invoice_terms_en' => ['required', 'string', 'max:10000'],
            'invoice_footer_ar' => ['nullable', 'string', 'max:2000'],
            'invoice_footer_en' => ['nullable', 'string', 'max:2000'],
            'whatsapp_invoice_ar' => $whatsapp,
            'whatsapp_invoice_en' => $whatsapp,
            'whatsapp_receipt_ar' => $whatsapp,
            'whatsapp_receipt_en' => $whatsapp,
            'payment_templates' => ['required', 'array', 'min:1', 'max:20'],
            'payment_templates.*.name_ar' => ['required', 'string', 'max:100'],
            'payment_templates.*.name_en' => ['nullable', 'string', 'max:100'],
            'payment_templates.*.lines' => ['required', 'array', 'min:1', 'max:20'],
            'payment_templates.*.lines.*.label_ar' => ['required', 'string', 'max:190'],
            'payment_templates.*.lines.*.label_en' => ['nullable', 'string', 'max:190'],
            'payment_templates.*.lines.*.percent' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    /** Each template's percentages must add up to exactly 100.00. */
    public function after(): array
    {
        return [function (Validator $validator) {
            foreach ((array) $this->input('payment_templates', []) as $index => $template) {
                $sum = '0';

                foreach ((array) ($template['lines'] ?? []) as $line) {
                    $sum = bcadd($sum, is_numeric($line['percent'] ?? null) ? (string) $line['percent'] : '0', 2);
                }

                if (bccomp($sum, '100', 2) !== 0) {
                    $validator->errors()->add("payment_templates.{$index}", __('settings.templates.sum_not_100', ['sum' => $sum]));
                }
            }
        }];
    }

    public function values(): array
    {
        $data = $this->validated();

        $data['payment_templates'] = array_values(array_map(fn (array $template) => [
            'name_ar' => $template['name_ar'],
            'name_en' => $template['name_en'] ?? null,
            'lines' => array_values(array_map(fn (array $line) => [
                'label_ar' => $line['label_ar'],
                'label_en' => $line['label_en'] ?? null,
                'percent' => number_format((float) $line['percent'], 2, '.', ''),
            ], $template['lines'])),
        ], $data['payment_templates']));

        return $data;
    }
}
