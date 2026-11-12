<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('finance.currency_code', 'AED');
        $this->migrator->add('finance.currency_symbol_ar', 'د.إ');
        $this->migrator->add('finance.currency_symbol_en', 'AED');
        $this->migrator->add('finance.decimals', 2);
        $this->migrator->add('finance.vat_rate', '5.00');
        $this->migrator->add('finance.quotation_validity_days', 30);
        $this->migrator->add('finance.invoice_due_days', 14);
    }
};
