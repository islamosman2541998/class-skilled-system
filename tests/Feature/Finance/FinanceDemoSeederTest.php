<?php

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Receipt;
use App\Models\Role;
use App\Models\User;
use App\Support\ReportRegistry;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\FinanceDemoSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\ProjectsDemoSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SalesDemoSeeder;

use function Pest\Laravel\actingAs;

it('seeds finance demo data that renders every finance page', function () {
    $this->seed([RolesSeeder::class, LookupSeeder::class, DocumentSequenceSeeder::class]);
    $admin = User::factory()->create()->assignRole('super-admin');
    User::factory()->count(3)->create()->each->assignRole('engineer');
    User::factory()->count(2)->create()->each->assignRole('sales');
    User::factory()->create()->assignRole('accountant');

    $this->seed([SalesDemoSeeder::class, ProjectsDemoSeeder::class, FinanceDemoSeeder::class]);

    $statuses = Invoice::query()->pluck('status')->map(fn (InvoiceStatus $status) => $status->value)->unique()->sort()->values()->all();
    $replacement = Invoice::query()->whereNotNull('replaces_id')->first();

    expect($statuses)->toBe(collect(InvoiceStatus::cases())->map->value->sort()->values()->all())
        ->and(Invoice::query()->overdue()->count())->toBeGreaterThanOrEqual(2)
        ->and($replacement)->not->toBeNull()
        ->and(Invoice::query()->find($replacement->replaces_id)->status)->toBe(InvoiceStatus::Cancelled)
        ->and(Receipt::query()->active()->get()->filter(fn (Receipt $receipt) => bccomp($receipt->unallocated(), '0', 2) > 0)->isNotEmpty())->toBeTrue()
        ->and(Receipt::query()->where('status', 'cancelled')->exists())->toBeTrue()
        ->and(Expense::query()->count())->toBe(40)
        ->and(Expense::query()->whereNull('project_id')->count())->toBeGreaterThanOrEqual(5)
        ->and(Expense::query()->distinct()->count('category_id'))->toBeGreaterThanOrEqual(3);

    actingAs($admin);

    foreach (['home', 'invoices.index', 'invoices.create', 'receipts.index', 'receipts.create', 'expenses.index', 'expenses.create', 'expenses.quick', 'reports.index'] as $route) {
        $this->get(route($route))->assertOk();
    }

    foreach (['profitability', 'aging', 'collections', 'expenses', 'vat', 'sales', 'late', 'statement', 'installments'] as $report) {
        expect(ReportRegistry::find($report))->not->toBeNull();
        $this->get(route('reports.show', $report))->assertOk();
    }

    $invoice = Invoice::query()->whereNotNull('number')->firstOrFail();
    $receipt = Receipt::query()->firstOrFail();
    $client = Client::query()->whereHas('invoices', fn ($query) => $query->whereNotNull('number'))->firstOrFail();

    $this->get(route('invoices.show', $invoice))->assertOk();
    $this->get(route('invoices.pdf', [$invoice, 'lang' => 'both']))->assertOk();
    $this->get(route('receipts.show', $receipt))->assertOk();
    $this->get(route('receipts.pdf', [$receipt, 'lang' => 'both']))->assertOk();
    $this->get(route('expenses.show', Expense::query()->firstOrFail()))->assertOk();
    $this->get(route('clients.statement.pdf', [$client, 'lang' => 'both']))->assertOk();
    $this->get(route('reports.show', ['statement', 'f' => ['client_id' => $client->id]]))->assertOk();
});

it('gives the default roles their starting finance permissions', function () {
    $this->seed(RolesSeeder::class);

    $management = Role::findByName('management');
    $accountant = Role::findByName('accountant');
    $engineer = Role::findByName('engineer');
    $sales = Role::findByName('sales');

    expect($management->hasPermissionTo('invoices.force-delete'))->toBeTrue()
        ->and($management->hasPermissionTo('finance.profitability'))->toBeTrue()
        ->and($management->hasPermissionTo('reports.export'))->toBeTrue()
        ->and($accountant->hasPermissionTo('receipts.cancel'))->toBeTrue()
        ->and($accountant->hasPermissionTo('finance.costs'))->toBeTrue()
        ->and($accountant->hasPermissionTo('invoices.force-delete'))->toBeFalse()
        ->and($accountant->hasPermissionTo('expenses.force-delete'))->toBeFalse()
        ->and($engineer->hasPermissionTo('expenses.create'))->toBeTrue()
        ->and($engineer->hasPermissionTo('expenses.view'))->toBeTrue()
        ->and($engineer->hasPermissionTo('finance.costs'))->toBeFalse()
        ->and($engineer->hasPermissionTo('invoices.view'))->toBeFalse()
        ->and($sales->hasPermissionTo('reports.view'))->toBeTrue()
        ->and($sales->hasPermissionTo('invoices.view'))->toBeFalse();
});
