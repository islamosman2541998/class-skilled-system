<?php

use App\Models\User;
use App\Settings\AppearanceSettings;
use App\Support\Menu;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('renders rtl for an Arabic user', function () {
    actingAs(User::factory()->create(['locale' => 'ar']))->get('/')
        ->assertOk()
        ->assertSee('<html lang="ar" dir="rtl"', false);
});

it('renders ltr for an English user', function () {
    actingAs(User::factory()->create(['locale' => 'en']))->get('/')
        ->assertOk()
        ->assertSee('<html lang="en" dir="ltr"', false);
});

it('uses the default locale setting for guests', function () {
    $settings = app(AppearanceSettings::class);
    $settings->default_locale = 'en';
    $settings->save();

    get('/login')->assertSee('<html lang="en" dir="ltr"', false);
});

it('saves the switched locale on the user', function () {
    $user = User::factory()->create(['locale' => 'ar']);

    actingAs($user)->post('/locale/en')->assertRedirect();

    expect($user->fresh()->locale)->toBe('en');
});

it('rejects an unknown locale', function () {
    actingAs(User::factory()->create())->post('/locale/fr')->assertNotFound();
});

it('prints the theme variables in the page', function () {
    actingAs(User::factory()->create())->get('/')->assertSee('--c-navy:#2B4168', false);
});

it('shows flashed messages to the toast script', function () {
    actingAs(User::factory()->create())->withSession(['success' => 'تم الحفظ'])->get('/')
        ->assertSee('id="flash-data"', false);
});

it('lists the home item in the menu', function () {
    $groups = Menu::items(User::factory()->create());

    expect(collect($groups)->pluck('items')->flatten(1)->pluck('route'))->toContain('home');
});

it('hides menu items the user cannot access', function () {
    Menu::register('main', ['label' => 'app.home', 'icon' => 'lock', 'route' => 'home', 'permission' => 'nothing.allowed']);

    $routes = collect(Menu::items(User::factory()->create()))->pluck('items')->flatten(1);

    expect($routes->where('permission', 'nothing.allowed'))->toBeEmpty();
});
