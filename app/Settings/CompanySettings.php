<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class CompanySettings extends Settings
{
    public string $name_ar;

    public string $name_en;

    public ?string $trn;

    public ?string $license_no;

    public ?string $address_ar;

    public ?string $address_en;

    /** @var list<string> */
    public array $phones;

    public ?string $email;

    public ?string $bank_name;

    public ?string $bank_account_name;

    public ?string $bank_iban;

    public ?string $logo_path;

    public ?string $login_logo_path;

    public ?string $stamp_path;

    public ?string $signature_path;

    public static function group(): string
    {
        return 'company';
    }
}
