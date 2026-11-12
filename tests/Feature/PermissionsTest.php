<?php

use App\Models\Role;
use App\Models\User;
use App\Support\Menu;
use App\Support\PermissionRegistry;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RolesSeeder;
use Spatie\Permission\Models\Permission;

it('syncs every registered permission once', function () {
    PermissionRegistry::sync();
    PermissionRegistry::sync();

    expect(Permission::where('name', 'users.force-delete')->count())->toBe(1)
        ->and(Permission::count())->toBe(count(PermissionRegistry::all()));
});

it('registers the stage 0 modules', function () {
    expect(PermissionRegistry::all())
        ->toContain('users.view', 'roles.update', 'lookups.delete', 'settings.manage', 'activity.view', 'translations.manage')
        ->not->toContain('settings.view');
});

it('lets super-admin pass any gate', function () {
    $this->seed(RolesSeeder::class);
    $admin = User::factory()->create()->assignRole('super-admin');

    expect($admin->can('anything.at-all'))->toBeTrue()
        ->and(User::factory()->create()->can('anything.at-all'))->toBeFalse();
});

it('reports record scope from roles', function () {
    $this->seed(RolesSeeder::class);
    $engineer = User::factory()->create()->assignRole('engineer');
    $both = User::factory()->create()->assignRole('engineer', 'sales');

    expect($engineer->seesAllRecords())->toBeFalse()
        ->and($both->seesAllRecords())->toBeTrue()
        ->and(User::factory()->create()->seesAllRecords())->toBeFalse();
});

it('hides menu items the user cannot view', function () {
    Menu::register('admin', ['label' => 'app.profile', 'icon' => 'users', 'route' => 'profile.edit', 'permission' => 'users.view']);

    $routesFor = fn (User $user) => collect(Menu::items($user))->pluck('items')->flatten(1)->pluck('route');

    expect($routesFor(userWith()))->not->toContain('profile.edit')
        ->and($routesFor(userWith('users.view')))->toContain('profile.edit');
});

it('seeds the five roles with Arabic labels', function () {
    $this->seed(RolesSeeder::class);

    $engineer = Role::findByName('engineer');

    expect(Role::count())->toBe(5)
        ->and($engineer->label_ar)->toBe('مهندس مشروع')
        ->and($engineer->scope)->toBe('assigned')
        ->and(Role::findByName('super-admin')->is_system)->toBeTrue()
        ->and(Role::findByName('accountant')->label_ar)->toBe('محاسب');
});

it('creates the admin from the configured values', function () {
    config(['system.admin' => ['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'a-long-test-secret']]);

    $this->seed(RolesSeeder::class);
    $this->seed(AdminUserSeeder::class);

    expect(User::where('email', 'owner@example.test')->first()?->hasRole('super-admin'))->toBeTrue();
});

it('refuses to seed an admin without credentials outside testing', function () {
    config(['system.admin' => ['name' => null, 'email' => null, 'password' => null]]);
    app()->detectEnvironment(fn () => 'local');

    $this->seed(RolesSeeder::class);

    expect(fn () => $this->seed(AdminUserSeeder::class))->toThrow(RuntimeException::class);
});
