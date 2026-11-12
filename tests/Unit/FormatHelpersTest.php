<?php

use App\Settings\FinanceSettings;
use Illuminate\Support\Carbon;

it('formats money in Arabic', function () {
    expect(money('1234.5'))->toBe("1,234.50\u{00A0}د.إ");
});

it('formats money in English', function () {
    app()->setLocale('en');

    expect(money(1234.5))->toBe("1,234.50\u{00A0}AED");
});

it('omits the currency on request', function () {
    expect(money('1234.5', false))->toBe('1,234.50');
});

it('follows the decimals setting', function () {
    $settings = app(FinanceSettings::class);
    $settings->decimals = 3;
    $settings->save();

    expect(money(1, false))->toBe('1.000');
});

it('treats null as zero', function () {
    expect(money(null, false))->toBe('0.00');
});

it('formats dates with western digits', function () {
    expect(fmt_date(Carbon::parse('2026-11-12')))->toBe('12/11/2026')
        ->and(fmt_datetime(Carbon::parse('2026-11-12 09:05')))->toBe('12/11/2026 09:05')
        ->and(fmt_date(null))->toBe('');
});
