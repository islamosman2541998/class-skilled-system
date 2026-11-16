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

    /** Default retention percent copied into new projects (spec §5.9). */
    public string $retention_percent;

    /** Working hours in a day, for the overtime hour cost (spec §8.17). */
    public int $labour_hours_per_day;

    /** Overtime hour = daily wage ÷ working hours × this multiplier. */
    public string $overtime_multiplier;

    public static function group(): string
    {
        return 'finance';
    }
}
