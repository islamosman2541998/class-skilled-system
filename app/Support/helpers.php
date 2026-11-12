<?php

use App\Settings\FinanceSettings;

if (! function_exists('money')) {
    /**
     * Format an amount with the configured decimals and, optionally, the currency symbol of the current locale.
     */
    function money(string|int|float|null $amount, bool $withCurrency = true): string
    {
        $settings = app(FinanceSettings::class);
        $formatted = number_format((float) ($amount ?? 0), $settings->decimals, '.', ',');

        if (! $withCurrency) {
            return $formatted;
        }

        $symbol = app()->getLocale() === 'ar' ? $settings->currency_symbol_ar : $settings->currency_symbol_en;

        return $formatted."\u{00A0}".$symbol;
    }
}

if (! function_exists('fmt_date')) {
    function fmt_date(?DateTimeInterface $date): string
    {
        return $date ? $date->format('d/m/Y') : '';
    }
}

if (! function_exists('fmt_datetime')) {
    function fmt_datetime(?DateTimeInterface $date): string
    {
        return $date ? $date->format('d/m/Y H:i') : '';
    }
}
