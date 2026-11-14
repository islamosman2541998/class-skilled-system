<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('templates.invoice_terms_ar', implode("\n", [
            '1. يرجى السداد قبل تاريخ الاستحقاق المذكور.',
            '2. المبالغ شاملة ضريبة القيمة المضافة كما هو موضح.',
        ]));
        $this->migrator->add('templates.invoice_terms_en', implode("\n", [
            '1. Please pay before the stated due date.',
            '2. Amounts include value added tax as shown.',
        ]));
        $this->migrator->add('templates.invoice_footer_ar', null);
        $this->migrator->add('templates.invoice_footer_en', null);
        $this->migrator->add('templates.whatsapp_invoice_ar', 'السيد :client، مرفق الفاتورة رقم :number بقيمة :total، تستحق في :due. :link');
        $this->migrator->add('templates.whatsapp_invoice_en', 'Dear :client, please find invoice :number for :total, due on :due. :link');
        $this->migrator->add('templates.whatsapp_receipt_ar', 'السيد :client، مرفق سند القبض رقم :number بمبلغ :total. شكرا لكم. :link');
        $this->migrator->add('templates.whatsapp_receipt_en', 'Dear :client, please find receipt :number for :total. Thank you. :link');
    }
};
