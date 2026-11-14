<?php

use App\Support\TranslationCatalog;
use Spatie\TranslationLoader\LanguageLine;

use function Pest\Laravel\actingAs;

it('overrides a label from the database', function () {
    actingAs(userWith(['translations.manage']))
        ->putJson(route('translations.update'), ['group' => 'app', 'key' => 'save', 'ar' => 'حفظ التغييرات', 'en' => 'Save changes'])
        ->assertOk();

    app('translator')->setLoaded([]);

    expect(__('app.save'))->toBe('حفظ التغييرات')
        ->and(__('app.save', [], 'en'))->toBe('Save changes');
});

it('overrides a nested key', function () {
    actingAs(userWith(['translations.manage']))
        ->putJson(route('translations.update'), ['group' => 'uploads', 'key' => 'kinds.image', 'ar' => 'صورة فوتوغرافية', 'en' => 'Photo'])
        ->assertOk();

    app('translator')->setLoaded([]);

    expect(__('uploads.kinds.image'))->toBe('صورة فوتوغرافية')
        ->and(__('uploads.kinds.video'))->toBe('فيديو');
});

it('restores the file value on reset', function () {
    $user = userWith(['translations.manage']);
    actingAs($user)->putJson(route('translations.update'), ['group' => 'app', 'key' => 'save', 'ar' => 'احفظ', 'en' => 'Store']);

    actingAs($user)->deleteJson(route('translations.reset'), ['group' => 'app', 'key' => 'save'])->assertOk();
    app('translator')->setLoaded([]);

    expect(__('app.save'))->toBe('حفظ')->and(LanguageLine::count())->toBe(0);
});

it('rejects an unknown key or group', function () {
    $user = userWith(['translations.manage']);

    actingAs($user)->putJson(route('translations.update'), ['group' => 'app', 'key' => 'nope.nothing', 'ar' => 'x', 'en' => 'x'])->assertUnprocessable();
    actingAs($user)->putJson(route('translations.update'), ['group' => 'validation', 'key' => 'required', 'ar' => 'x', 'en' => 'x'])->assertUnprocessable();
});

it('lists every key of the app groups in both languages', function () {
    $row = app(TranslationCatalog::class)->all()->first(fn ($row) => $row['group'] === 'app' && $row['key'] === 'save');

    expect($row)->toMatchArray(['ar' => 'حفظ', 'en' => 'Save', 'overridden' => false]);
});

it('marks overridden keys', function () {
    LanguageLine::create(['group' => 'app', 'key' => 'save', 'text' => ['ar' => 'احفظ', 'en' => 'Store']]);

    $row = app(TranslationCatalog::class)->all()->first(fn ($row) => $row['group'] === 'app' && $row['key'] === 'save');

    expect($row)->toMatchArray(['ar' => 'احفظ', 'en' => 'Store', 'overridden' => true]);
});

it('has an English and an Arabic value for every key', function () {
    $missing = app(TranslationCatalog::class)->missing();

    expect($missing)->toBe([]);
});

it('returns 403 without translations.manage', function () {
    actingAs(userWith())->get(route('translations.index'))->assertForbidden();
    actingAs(userWith())->putJson(route('translations.update'), ['group' => 'app', 'key' => 'save', 'ar' => 'x', 'en' => 'x'])->assertForbidden();
});

it('renders the editor', function () {
    actingAs(userWith(['translations.manage']))->get(route('translations.index'))->assertOk()->assertSee('app.save');
});
