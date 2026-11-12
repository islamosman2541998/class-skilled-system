<?php

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
