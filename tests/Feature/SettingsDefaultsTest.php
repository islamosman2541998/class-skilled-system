<?php

use App\Settings\AppearanceSettings;
use App\Settings\CompanySettings;
use App\Settings\FinanceSettings;
use App\Settings\SystemSettings;
use App\Settings\UploadSettings;

it('has the spec defaults', function () {
    expect(app(FinanceSettings::class)->vat_rate)->toBe('5.00')
        ->and(app(FinanceSettings::class)->currency_code)->toBe('AED')
        ->and(app(AppearanceSettings::class)->primary_font)->toBe('cairo')
        ->and(app(AppearanceSettings::class)->color_navy)->toBe('#2B4168')
        ->and(app(CompanySettings::class)->name_ar)->toBe('كلاس سكيلد للمقاولات العامة')
        ->and(app(UploadSettings::class)->image_max_dimension)->toBe(2000)
        ->and(app(UploadSettings::class)->video_types)->toBe(['mp4', 'webm'])
        ->and(app(SystemSettings::class)->trash_retention_days)->toBeNull()
        ->and(app(SystemSettings::class)->session_idle_minutes)->toBe(120);
});
