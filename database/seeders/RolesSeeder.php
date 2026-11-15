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
        'management' => ['الإدارة', 'Management', 'all', false, [
            'users.view', 'roles.view', 'lookups.view', 'activity.view',
            'clients.view', 'clients.create', 'clients.update', 'clients.delete', 'clients.restore', 'clients.export', 'clients.print',
            'leads.view', 'leads.create', 'leads.update', 'leads.delete', 'leads.restore', 'leads.export', 'leads.print',
            'site_visits.view', 'site_visits.create', 'site_visits.update', 'site_visits.delete', 'site_visits.restore', 'site_visits.export',
            'price_items.view', 'price_items.create', 'price_items.update', 'price_items.delete', 'price_items.restore', 'price_items.export',
            'quotations.view', 'quotations.create', 'quotations.update', 'quotations.delete', 'quotations.restore', 'quotations.export', 'quotations.print', 'quotations.approve',
            'projects.view', 'projects.create', 'projects.update', 'projects.delete', 'projects.restore', 'projects.export', 'projects.print', 'projects.approve',
            'maintenance.view', 'maintenance.create', 'maintenance.update', 'maintenance.delete', 'maintenance.restore', 'maintenance.export',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.restore', 'invoices.force-delete', 'invoices.export', 'invoices.print', 'invoices.cancel',
            'receipts.view', 'receipts.create', 'receipts.update', 'receipts.export', 'receipts.print', 'receipts.cancel',
            'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete', 'expenses.restore', 'expenses.force-delete', 'expenses.export', 'expenses.print',
            'reports.view', 'reports.export', 'finance.costs', 'finance.profitability',
        ]],
        'sales' => ['مبيعات', 'Sales', 'all', false, [
            'lookups.view',
            'clients.view', 'clients.create', 'clients.update', 'clients.export',
            'leads.view', 'leads.create', 'leads.update', 'leads.export',
            'site_visits.view', 'site_visits.create', 'site_visits.update',
            'price_items.view',
            'quotations.view', 'quotations.create', 'quotations.update', 'quotations.export', 'quotations.print',
            'reports.view',
        ]],
        'engineer' => ['مهندس مشروع', 'Project engineer', 'assigned', false, [
            'clients.view', 'leads.view', 'site_visits.view', 'site_visits.update', 'quotations.view',
            'projects.view', 'projects.update', 'maintenance.view', 'maintenance.create', 'maintenance.update',
            'expenses.view', 'expenses.create',
        ]],
        'accountant' => ['محاسب', 'Accountant', 'all', false, [
            'lookups.view', 'clients.view', 'price_items.view', 'quotations.view', 'quotations.export', 'quotations.print',
            'projects.view', 'projects.print',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.restore', 'invoices.export', 'invoices.print', 'invoices.cancel',
            'receipts.view', 'receipts.create', 'receipts.update', 'receipts.export', 'receipts.print', 'receipts.cancel',
            'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete', 'expenses.restore', 'expenses.export', 'expenses.print',
            'reports.view', 'reports.export', 'finance.costs', 'finance.profitability',
        ]],
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
