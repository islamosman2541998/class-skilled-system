<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    /**
     * name => [label_ar, label_en, scope, is_system, permissions]
     * Permissions are the starting set; they stay editable from the roles screen.
     */
    private const ROLES = [
        'super-admin' => ['مدير النظام', 'System administrator', 'all', true, []],
        'management' => ['الإدارة', 'Management', 'all', false, ['users.view', 'roles.view', 'lookups.view', 'activity.view']],
        'sales' => ['مبيعات', 'Sales', 'all', false, ['lookups.view']],
        'engineer' => ['مهندس مشروع', 'Project engineer', 'assigned', false, []],
        'accountant' => ['محاسب', 'Accountant', 'all', false, ['lookups.view']],
    ];

    public function run(): void
    {
        PermissionRegistry::sync();

        foreach (self::ROLES as $name => [$labelAr, $labelEn, $scope, $isSystem, $permissions]) {
            $role = Role::query()->firstOrNew(['name' => $name, 'guard_name' => 'web']);
            $isNew = ! $role->exists;

            $role->forceFill([
                'label_ar' => $role->label_ar ?: $labelAr,
                'label_en' => $role->label_en ?: $labelEn,
                'scope' => $isNew ? $scope : $role->scope,
                'is_system' => $isSystem,
            ])->save();

            if ($isNew && $permissions !== []) {
                $role->givePermissionTo($permissions);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
