<?php

use App\Models\Client;
use App\Models\Lead;
use App\Models\PriceItem;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SiteVisit;
use App\Models\User;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SalesDemoSeeder;

use function Pest\Laravel\actingAs;

it('seeds sales demo data that renders every sales list', function () {
    $this->seed([RolesSeeder::class, LookupSeeder::class, DocumentSequenceSeeder::class]);
    $admin = User::factory()->create()->assignRole('super-admin');
    User::factory()->count(3)->create()->each->assignRole('engineer');
    User::factory()->count(2)->create()->each->assignRole('sales');

    $this->seed(SalesDemoSeeder::class);

    expect(Client::query()->count())->toBeGreaterThanOrEqual(40)
        ->and(Lead::query()->count())->toBeGreaterThanOrEqual(80)
        ->and(SiteVisit::query()->count())->toBeGreaterThanOrEqual(30)
        ->and(PriceItem::query()->count())->toBeGreaterThanOrEqual(60)
        ->and(Quotation::query()->count())->toBeGreaterThanOrEqual(35)
        ->and(Quotation::query()->where('revision', '>', 0)->exists())->toBeTrue();

    actingAs($admin);
    foreach (['clients.index', 'leads.index', 'site-visits.index', 'price-items.index', 'quotations.index', 'my-visits'] as $route) {
        $this->get(route($route))->assertOk();
    }

    $quotation = Quotation::query()->first();
    $this->get(route('quotations.show', $quotation))->assertOk();
    $this->get(route('quotations.pdf', $quotation))->assertOk();
    $this->get(route('clients.show', $quotation->client_id))->assertOk();
    $this->get(route('leads.show', Lead::query()->first()))->assertOk();
});

it('gives the default roles their starting sales permissions', function () {
    $this->seed(RolesSeeder::class);

    expect(Role::findByName('sales')->hasPermissionTo('quotations.create'))->toBeTrue()
        ->and(Role::findByName('sales')->hasPermissionTo('quotations.approve'))->toBeFalse()
        ->and(Role::findByName('management')->hasPermissionTo('quotations.approve'))->toBeTrue()
        ->and(Role::findByName('engineer')->hasPermissionTo('site_visits.update'))->toBeTrue()
        ->and(Role::findByName('accountant')->hasPermissionTo('quotations.print'))->toBeTrue();
});
