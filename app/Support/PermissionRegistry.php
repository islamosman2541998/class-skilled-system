<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * The single list of permissions. Each stage registers its modules here; the role form
 * builds its matrix from modules() and sync() keeps the database in step.
 */
class PermissionRegistry
{
    public const ACTIONS = ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export', 'print'];

    /** @var array<string, list<string>> */
    private static array $modules = [
        'users' => ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export'],
        'roles' => ['view', 'create', 'update', 'delete'],
        'lookups' => ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export'],
        'settings' => ['manage'],
        'activity' => ['view', 'export'],
        'translations' => ['manage'],
    ];

    /**
     * @param  list<string>  $actions
     */
    public static function register(string $module, array $actions = self::ACTIONS): void
    {
        self::$modules[$module] = array_values(array_unique([...(self::$modules[$module] ?? []), ...$actions]));
    }

    /**
     * @return array<string, list<string>>
     */
    public static function modules(): array
    {
        return self::$modules;
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $names = [];

        foreach (self::$modules as $module => $actions) {
            foreach ($actions as $action) {
                $names[] = "{$module}.{$action}";
            }
        }

        return $names;
    }

    /**
     * Create any permission that is registered but missing from the database.
     */
    public static function sync(): void
    {
        $existing = Permission::query()->where('guard_name', 'web')->pluck('name')->all();
        $missing = array_diff(self::all(), $existing);

        foreach ($missing as $name) {
            Permission::create(['name' => $name, 'guard_name' => 'web']);
        }

        if ($missing !== []) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
