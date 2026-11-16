<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    /** WhatsApp message for progress claims (spec §5.10, §8.14). */
    public function up(): void
    {
        $this->migrator->add('templates.whatsapp_claim_ar', 'السيد :client، مرفق المستخلص رقم :number بقيمة :total. :link');
        $this->migrator->add('templates.whatsapp_claim_en', 'Dear :client, please find progress claim :number for :total. :link');
    }
};
