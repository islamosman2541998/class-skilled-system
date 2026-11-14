<?php

use App\Models\Role;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * A user holding exactly the given permissions (created on demand).
 *
 * @param  string|list<string>  $permissions
 */
function userWith(string|array $permissions = [], array $attributes = []): User
{
    PermissionRegistry::sync();

    $user = User::factory()->create($attributes);

    foreach ((array) $permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user->givePermissionTo((array) $permissions);
}

/**
 * A user whose permissions come from one role with the given record scope (spec §6):
 * "all" sees every record, "assigned" only the records assigned to them.
 *
 * @param  list<string>  $permissions
 */
function scopedUser(array $permissions = [], string $scope = 'all', array $attributes = []): User
{
    PermissionRegistry::sync();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::create([
        'name' => 'test-'.$scope.'-'.uniqid(),
        'guard_name' => 'web',
        'label_ar' => 'دور تجريبي',
        'scope' => $scope,
    ]);
    $role->givePermissionTo($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::factory()->create($attributes)->assignRole($role);
}
