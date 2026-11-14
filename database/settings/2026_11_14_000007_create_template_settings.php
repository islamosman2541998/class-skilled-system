<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('templates.quotation_terms_ar', implode("\n", [
            '1. العرض صالح حتى التاريخ المذكور فيه.',
            '2. الدفع حسب جدول الدفعات المرفق.',
            '3. الأسعار شاملة ضريبة القيمة المضافة.',
            '4. أي أعمال خارج البنود المذكورة تعتبر أعمالا إضافية ويصدر بها أمر تغيير.',
        ]));
        $this->migrator->add('templates.quotation_terms_en', implode("\n", [
            '1. This quotation is valid until the date stated on it.',
            '2. Payment follows the attached payment schedule.',
            '3. Prices include value added tax.',
            '4. Any work outside the listed items is additional and needs a variation order.',
        ]));
        $this->migrator->add('templates.quotation_footer_ar', null);
        $this->migrator->add('templates.quotation_footer_en', null);
        $this->migrator->add('templates.whatsapp_quotation_ar', 'السيد :client، مرفق عرض السعر رقم :number بقيمة :total. :link');
        $this->migrator->add('templates.whatsapp_quotation_en', 'Dear :client, please find quotation :number for :total. :link');
        $this->migrator->add('templates.payment_templates', [
            [
                'name_ar' => '30 / 40 / 30',
                'name_en' => '30 / 40 / 30',
                'lines' => [
                    ['label_ar' => 'دفعة مقدمة', 'label_en' => 'Advance payment', 'percent' => '30.00'],
                    ['label_ar' => 'عند منتصف الأعمال', 'label_en' => 'At mid-works', 'percent' => '40.00'],
                    ['label_ar' => 'عند التسليم', 'label_en' => 'On handover', 'percent' => '30.00'],
                ],
            ],
        ]);
    }
};
