<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('company.name_ar', 'كلاس سكيلد للمقاولات العامة');
        $this->migrator->add('company.name_en', 'Class Skilled General Contracting');
        $this->migrator->add('company.trn', null);
        $this->migrator->add('company.license_no', null);
        $this->migrator->add('company.address_ar', null);
        $this->migrator->add('company.address_en', null);
        $this->migrator->add('company.phones', []);
        $this->migrator->add('company.email', null);
        $this->migrator->add('company.bank_name', null);
        $this->migrator->add('company.bank_account_name', null);
        $this->migrator->add('company.bank_iban', null);
        $this->migrator->add('company.logo_path', null);
        $this->migrator->add('company.login_logo_path', null);
        $this->migrator->add('company.stamp_path', null);
        $this->migrator->add('company.signature_path', null);
    }
};
