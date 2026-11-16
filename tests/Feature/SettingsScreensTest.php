<?php

use App\Settings\AppearanceSettings;
use App\Settings\CompanySettings;
use App\Settings\FinanceSettings;
use App\Settings\SystemSettings;
use App\Settings\UploadSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

function settingsAdmin()
{
    return userWith(['settings.manage']);
}

function appearancePayload(array $overrides = []): array
{
    return $overrides + [
        'color_navy' => '#2B4168', 'color_deep' => '#0C122B', 'color_sky' => '#4FC3EE', 'color_ink' => '#24324D',
        'color_muted' => '#5D6A80', 'color_line' => '#E3E8EF', 'color_paper' => '#F5F7FA',
        'primary_font' => 'cairo', 'heading_font' => 'same', 'default_locale' => 'ar',
    ];
}

function financePayload(array $overrides = []): array
{
    return $overrides + [
        'currency_code' => 'AED', 'currency_symbol_ar' => 'د.إ', 'currency_symbol_en' => 'AED', 'decimals' => 2,
        'vat_rate' => '5', 'quotation_validity_days' => 30, 'invoice_due_days' => 14, 'retention_percent' => '5', 'labour_hours_per_day' => 8, 'overtime_multiplier' => '1.25',
    ];
}

it('returns 403 without settings.manage', function () {
    $user = userWith();

    actingAs($user)->get(route('settings.index'))->assertForbidden();
    actingAs($user)->get(route('settings.edit', 'company'))->assertForbidden();
    actingAs($user)->put(route('settings.update', 'company'), ['name_ar' => 'x'])->assertForbidden();
});

it('returns 404 for an unknown group', function () {
    actingAs(settingsAdmin())->get('/settings/secrets')->assertNotFound();
});

it('opens every settings screen', function (string $group) {
    actingAs(settingsAdmin())->get(route('settings.edit', $group))->assertOk();
})->with(['company', 'appearance', 'finance', 'uploads', 'system']);

it('lists the settings groups and the managed lists', function () {
    actingAs(userWith(['settings.manage', 'lookups.view']))->get(route('settings.index'))->assertOk()
        ->assertSee(route('settings.edit', 'finance'), false)
        ->assertSee(route('lookups.index', 'service'), false);
});

it('saves company data and it is readable on the next request', function () {
    actingAs(settingsAdmin())->put(route('settings.update', 'company'), [
        'name_ar' => 'كلاس سكيلد', 'name_en' => 'Class Skilled', 'trn' => '100123456700003',
        'phones' => "+971 58 982 0017\n+971 52 764 9086\n", 'email' => 'info@example.test', 'bank_iban' => 'AE070331234567890123456',
    ])->assertRedirect(route('settings.edit', 'company'))->assertSessionHasNoErrors();

    app()->forgetInstance(CompanySettings::class);
    $company = app(CompanySettings::class);

    expect($company->name_ar)->toBe('كلاس سكيلد')
        ->and($company->trn)->toBe('100123456700003')
        ->and($company->phones)->toBe(['+971 58 982 0017', '+971 52 764 9086'])
        ->and($company->address_ar)->toBeNull();
});

it('rejects a tax number that is not 15 digits', function () {
    actingAs(settingsAdmin())->put(route('settings.update', 'company'), ['name_ar' => 'x', 'name_en' => 'x', 'trn' => '12345'])
        ->assertSessionHasErrors('trn');
});

it('replaces the logo and deletes the old file', function () {
    Storage::fake('public');
    $admin = settingsAdmin();

    actingAs($admin)->put(route('settings.update', 'company'), ['name_ar' => 'x', 'name_en' => 'x', 'logo' => UploadedFile::fake()->image('one.png', 200, 80)]);
    app()->forgetInstance(CompanySettings::class);
    $first = app(CompanySettings::class)->logo_path;
    Storage::disk('public')->assertExists($first);

    actingAs($admin)->put(route('settings.update', 'company'), ['name_ar' => 'x', 'name_en' => 'x', 'logo' => UploadedFile::fake()->image('two.png', 200, 80)]);
    app()->forgetInstance(CompanySettings::class);
    $second = app(CompanySettings::class)->logo_path;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    actingAs($admin)->put(route('settings.update', 'company'), ['name_ar' => 'x', 'name_en' => 'x', 'remove_logo' => '1']);
    app()->forgetInstance(CompanySettings::class);
    expect(app(CompanySettings::class)->logo_path)->toBeNull();
    Storage::disk('public')->assertMissing($second);
});

it('rejects a logo that is not an image', function () {
    Storage::fake('public');

    actingAs(settingsAdmin())->put(route('settings.update', 'company'), [
        'name_ar' => 'x', 'name_en' => 'x', 'logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'),
    ])->assertSessionHasErrors('logo');
});

it('rejects an invalid colour', function () {
    actingAs(settingsAdmin())->put(route('settings.update', 'appearance'), appearancePayload(['color_navy' => 'blue']))
        ->assertSessionHasErrors('color_navy');

    actingAs(settingsAdmin())->put(route('settings.update', 'appearance'), appearancePayload(['color_sky' => '#12345G']))
        ->assertSessionHasErrors('color_sky');
});

it('accepts only cairo or tajawal as primary font', function () {
    actingAs(settingsAdmin())->put(route('settings.update', 'appearance'), appearancePayload(['primary_font' => 'arial']))
        ->assertSessionHasErrors('primary_font');

    actingAs(settingsAdmin())->put(route('settings.update', 'appearance'), appearancePayload(['primary_font' => 'tajawal', 'heading_font' => 'other']))
        ->assertSessionHasNoErrors();

    app()->forgetInstance(AppearanceSettings::class);
    expect(app(AppearanceSettings::class)->primary_font)->toBe('tajawal')
        ->and(app(AppearanceSettings::class)->heading_font)->toBe('other');
});

it('applies a changed primary colour to the page', function () {
    $admin = settingsAdmin();

    actingAs($admin)->put(route('settings.update', 'appearance'), appearancePayload(['color_navy' => '#112233']));
    app()->forgetInstance(AppearanceSettings::class);

    actingAs($admin)->get(route('home'))->assertSee('--c-navy:#112233', false);
});

it('rejects vat above 100 and decimals above 3', function () {
    actingAs(settingsAdmin())->put(route('settings.update', 'finance'), financePayload(['vat_rate' => '101', 'decimals' => 4]))
        ->assertSessionHasErrors(['vat_rate', 'decimals']);
});

it('stores the vat rate with two decimals', function () {
    actingAs(settingsAdmin())->put(route('settings.update', 'finance'), financePayload(['vat_rate' => '7.5']))->assertSessionHasNoErrors();

    app()->forgetInstance(FinanceSettings::class);
    expect(app(FinanceSettings::class)->vat_rate)->toBe('7.50');
});

it('saves upload limits and refuses executable file types', function () {
    $payload = [
        'image_max_mb' => 8, 'video_max_mb' => 300, 'file_max_mb' => 20, 'image_types' => 'jpg, PNG ,webp',
        'video_types' => 'mp4', 'file_types' => 'pdf,docx', 'image_max_dimension' => 1600, 'chunk_size_mb' => 2,
    ];

    actingAs(settingsAdmin())->put(route('settings.update', 'uploads'), $payload)->assertSessionHasNoErrors();
    app()->forgetInstance(UploadSettings::class);
    expect(app(UploadSettings::class)->image_types)->toBe(['jpg', 'png', 'webp'])
        ->and(app(UploadSettings::class)->video_max_mb)->toBe(300);

    actingAs(settingsAdmin())->put(route('settings.update', 'uploads'), ['file_types' => 'pdf,php'] + $payload)
        ->assertSessionHasErrors('file_types.1');
    actingAs(settingsAdmin())->put(route('settings.update', 'uploads'), ['image_types' => 'svg'] + $payload)
        ->assertSessionHasErrors('image_types.0');
});

it('saves system settings and allows an empty trash retention', function () {
    actingAs(settingsAdmin())->put(route('settings.update', 'system'), [
        'trash_retention_days' => '', 'session_idle_minutes' => 60, 'public_link_hours' => 48, 'backup_keep' => 7,
    ])->assertSessionHasNoErrors();

    app()->forgetInstance(SystemSettings::class);
    expect(app(SystemSettings::class)->trash_retention_days)->toBeNull()
        ->and(app(SystemSettings::class)->session_idle_minutes)->toBe(60);

    actingAs(settingsAdmin())->put(route('settings.update', 'system'), ['session_idle_minutes' => 2, 'public_link_hours' => 48])
        ->assertSessionHasErrors('session_idle_minutes');
});
