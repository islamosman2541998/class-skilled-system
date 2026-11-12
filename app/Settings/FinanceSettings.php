<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FinanceSettings extends Settings
{
    public string $currency_code;

    public string $currency_symbol_ar;

    public string $currency_symbol_en;

    public int $decimals;

    public string $vat_rate;

    public int $quotation_validity_days;

    public int $invoice_due_days;

    public static function group(): string
    {
        return 'finance';
    }
}
