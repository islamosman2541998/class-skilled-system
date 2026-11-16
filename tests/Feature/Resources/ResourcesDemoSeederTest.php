<?php

use App\Enums\PaymentVoucherStatus;
use App\Enums\PurchaseStatus;
use App\Models\Attendance;
use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\PaymentVoucher;
use App\Models\Purchase;
use App\Models\Role;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Worker;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\ProjectsDemoSeeder;
use Database\Seeders\ResourcesDemoSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SalesDemoSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

it('seeds resources demo data that renders every resources page', function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([RolesSeeder::class, LookupSeeder::class, DocumentSequenceSeeder::class]);
    $admin = User::factory()->create()->assignRole('super-admin');
    User::factory()->count(3)->create()->each->assignRole('engineer');
    User::factory()->count(2)->create()->each->assignRole('sales');

    $this->seed([SalesDemoSeeder::class, ProjectsDemoSeeder::class, ResourcesDemoSeeder::class]);

    expect(Supplier::query()->where('type', 'supplier')->count())->toBe(8)
        ->and(Supplier::query()->where('type', 'subcontractor')->count())->toBe(3)
        ->and(Purchase::query()->where('status', PurchaseStatus::Paid->value)->exists())->toBeTrue()
        ->and(Purchase::query()->where('status', PurchaseStatus::Partial->value)->exists())->toBeTrue()
        ->and(Purchase::query()->where('status', PurchaseStatus::Unpaid->value)->exists())->toBeTrue()
        ->and(Subcontract::query()->count())->toBe(2)
        ->and(Purchase::query()->whereNotNull('subcontract_id')->count())->toBe(3)
        ->and(PaymentVoucher::query()->where('status', PaymentVoucherStatus::Cancelled->value)->count())->toBe(1)
        ->and(Worker::query()->count())->toBe(12)
        ->and(Attendance::query()->count())->toBeGreaterThan(200)
        ->and(Equipment::query()->count())->toBe(5)
        ->and(EquipmentAssignment::query()->count())->toBe(7);

    actingAs($admin);
    foreach (['suppliers.index', 'purchases.index', 'subcontracts.index', 'payment-vouchers.index', 'workers.index', 'attendance.index', 'attendance.sheet', 'equipment.index'] as $route) {
        $this->get(route($route))->assertOk();
    }
    $this->get(route('reports.show', 'profitability'))->assertOk();

    $supplier = Supplier::query()->has('purchases')->firstOrFail();
    foreach (['overview', 'purchases', 'subcontracts', 'payment_vouchers', 'projects', 'attachments', 'activity'] as $tab) {
        $this->get(route('suppliers.show', [$supplier, 'tab' => $tab]))->assertOk();
    }
    $this->get(route('suppliers.statement.pdf', [$supplier, 'lang' => 'both']))->assertOk();
    $this->get(route('reports.show', ['supplier-statement', 'f' => ['supplier_id' => $supplier->id]]))->assertOk();

    foreach (Purchase::query()->get() as $purchase) {
        $this->get(route('purchases.show', $purchase))->assertOk();
    }
    foreach (Subcontract::query()->get() as $subcontract) {
        $this->get(route('subcontracts.show', $subcontract))->assertOk();
    }
    foreach (PaymentVoucher::query()->get() as $voucher) {
        $this->get(route('payment-vouchers.show', $voucher))->assertOk();
    }
    $this->get(route('equipment.show', Equipment::query()->firstOrFail()))->assertOk();

    $project = Attendance::query()->firstOrFail()->project;
    foreach (['purchases', 'resources'] as $tab) {
        $this->get(route('projects.show', [$project, 'tab' => $tab]))->assertOk();
    }
});

it('gives the default roles their starting resources permissions', function () {
    $this->seed(RolesSeeder::class);

    expect(Role::findByName('management')->hasPermissionTo('payment_vouchers.cancel'))->toBeTrue()
        ->and(Role::findByName('management')->hasPermissionTo('equipment.delete'))->toBeTrue()
        ->and(Role::findByName('accountant')->hasPermissionTo('payment_vouchers.create'))->toBeTrue()
        ->and(Role::findByName('accountant')->hasPermissionTo('attendance.create'))->toBeFalse()
        ->and(Role::findByName('accountant')->hasPermissionTo('attendance.view'))->toBeTrue()
        ->and(Role::findByName('engineer')->hasPermissionTo('attendance.create'))->toBeTrue()
        ->and(Role::findByName('engineer')->hasPermissionTo('purchases.create'))->toBeTrue()
        ->and(Role::findByName('engineer')->hasPermissionTo('payment_vouchers.view'))->toBeFalse();
});
