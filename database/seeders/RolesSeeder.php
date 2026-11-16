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
            'daily_reports.view', 'daily_reports.create', 'daily_reports.update', 'daily_reports.delete', 'daily_reports.restore', 'daily_reports.force-delete', 'daily_reports.export', 'daily_reports.print',
            'claims.view', 'claims.create', 'claims.update', 'claims.delete', 'claims.export', 'claims.print', 'claims.approve',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.delete', 'suppliers.restore', 'suppliers.export',
            'purchases.view', 'purchases.create', 'purchases.update', 'purchases.delete', 'purchases.restore', 'purchases.export', 'purchases.print',
            'subcontracts.view', 'subcontracts.create', 'subcontracts.update', 'subcontracts.delete', 'subcontracts.restore', 'subcontracts.export',
            'payment_vouchers.view', 'payment_vouchers.create', 'payment_vouchers.update', 'payment_vouchers.export', 'payment_vouchers.print', 'payment_vouchers.cancel',
            'workers.view', 'workers.create', 'workers.update', 'workers.delete', 'workers.restore', 'workers.export',
            'attendance.view', 'attendance.create', 'attendance.update', 'attendance.delete', 'attendance.export',
            'equipment.view', 'equipment.create', 'equipment.update', 'equipment.delete', 'equipment.restore', 'equipment.export',
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
            'daily_reports.view', 'daily_reports.create', 'daily_reports.update',
            'claims.view', 'claims.create', 'claims.update',
            'suppliers.view', 'purchases.view', 'purchases.create',
            'workers.view', 'attendance.view', 'attendance.create', 'attendance.update', 'equipment.view',
        ]],
        'accountant' => ['محاسب', 'Accountant', 'all', false, [
            'lookups.view', 'clients.view', 'price_items.view', 'quotations.view', 'quotations.export', 'quotations.print',
            'projects.view', 'projects.print',
            'invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.restore', 'invoices.export', 'invoices.print', 'invoices.cancel',
            'receipts.view', 'receipts.create', 'receipts.update', 'receipts.export', 'receipts.print', 'receipts.cancel',
            'expenses.view', 'expenses.create', 'expenses.update', 'expenses.delete', 'expenses.restore', 'expenses.export', 'expenses.print',
            'reports.view', 'reports.export', 'finance.costs', 'finance.profitability',
            'daily_reports.view',
            'claims.view', 'claims.export', 'claims.print',
            'suppliers.view', 'suppliers.create', 'suppliers.update', 'suppliers.export',
            'purchases.view', 'purchases.create', 'purchases.update', 'purchases.delete', 'purchases.export', 'purchases.print',
            'subcontracts.view', 'subcontracts.create', 'subcontracts.update', 'subcontracts.export',
            'payment_vouchers.view', 'payment_vouchers.create', 'payment_vouchers.update', 'payment_vouchers.export', 'payment_vouchers.print', 'payment_vouchers.cancel',
            'workers.view', 'workers.export', 'attendance.view', 'attendance.export', 'equipment.view', 'equipment.export',
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
