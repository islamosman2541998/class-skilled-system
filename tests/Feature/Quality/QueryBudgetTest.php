<?php

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\Client;
use App\Models\DailyReport;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\MaintenanceRequest;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Quotation;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Worker;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\actingAs;

/**
 * Spec §11: no N+1 queries. Each list page is loaded with 3 rows and again with 30; the number of
 * queries may not grow with the rows (a small allowance covers filter option lists).
 */
function seedRows(int $count): void
{
    $project = Project::factory()->create();

    for ($i = 0; $i < $count; $i++) {
        Client::factory()->create();
        Lead::factory()->create();
        Quotation::factory()->create();
        $row = Project::factory()->create(['billing_method' => 'claims']);
        MaintenanceRequest::factory()->create(['project_id' => $row->id]);
        Invoice::factory()->issued()->create(['project_id' => $row->id]);
        DailyReport::factory()->create(['project_id' => $row->id]);
        Claim::factory()->create(['project_id' => $row->id, 'client_id' => $row->client_id, 'status' => ClaimStatus::Submitted, 'number' => 'PC-T-'.fake()->unique()->numberBetween(1, 999999)]);
        Purchase::factory()->create(['project_id' => $project->id]);
        Subcontract::factory()->create(['project_id' => $project->id]);
        Supplier::factory()->create();
        Worker::factory()->create();
        Equipment::factory()->create();
    }
}

function queriesFor(string $url): int
{
    $count = 0;
    DB::listen(function () use (&$count) {
        $count++;
    });
    test()->get($url)->assertOk();
    DB::flushQueryLog();

    return $count;
}

it('list pages keep the same number of queries with 3 and 30 rows', function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    actingAs(User::factory()->create()->assignRole('super-admin'));
    $routes = ['clients.index', 'leads.index', 'quotations.index', 'projects.index', 'maintenance.index', 'invoices.index',
        'daily-reports.index', 'claims.index', 'suppliers.index', 'purchases.index', 'subcontracts.index', 'workers.index', 'equipment.index'];

    seedRows(3);
    $small = collect($routes)->mapWithKeys(fn (string $route) => [$route => queriesFor(route($route))]);
    seedRows(27);
    $large = collect($routes)->mapWithKeys(fn (string $route) => [$route => queriesFor(route($route))]);

    $grown = $large->filter(fn (int $count, string $route) => $count - $small[$route] > 3)
        ->map(fn (int $count, string $route) => $small[$route].' → '.$count)->all();

    expect($grown)->toBe([]);
});
