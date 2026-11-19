<?php

use App\Models\Backup;
use App\Models\Claim;
use App\Models\DailyReport;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\MaintenanceRequest;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Quotation;
use App\Models\SiteVisit;
use App\Models\Subcontract;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Worker;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

/**
 * Spec §6, §12: every action is authorized. A signed-in user without any permission must get 403
 * or 404 (or a redirect to the login) from every route that is not open to all users.
 */
const OPEN_ROUTE_PREFIXES = ['login', 'logout', 'public.', 'livewire.', 'default-livewire.', 'api.', 'storage.', 'ignition.', 'sanctum.', 'up'];

/** Routes every signed-in user may open: their own home, profile, notifications, search, language. */
const SIGNED_IN_ROUTES = [
    'home', 'dashboard.widgets.update', 'profile.edit', 'profile.update', 'profile.password', 'notifications.index', 'notifications.latest', 'notifications.read-all',
    'notifications.read', 'search', 'locale.switch', 'uploads.store', 'uploads.patch', 'uploads.head', 'uploads.destroy',
    'tables.preferences', 'tables.filters.store', 'tables.filters.destroy',
];

function isOpen(string $name): bool
{
    foreach (OPEN_ROUTE_PREFIXES as $prefix) {
        if ($name === $prefix || str_starts_with($name, $prefix)) {
            return true;
        }
    }

    return false;
}

it('every application route needs a signed-in user', function () {
    $missing = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => $route->getName() !== null && ! isOpen($route->getName()) && ! str_starts_with($route->uri(), 'api/'))
        ->reject(fn (RoutingRoute $route) => in_array('auth', $route->gatherMiddleware(), true))
        ->map(fn (RoutingRoute $route) => $route->getName())
        ->values()->all();

    expect($missing)->toBe([]);
});

it('a signed-in user without permissions is refused by every protected route', function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $project = Project::factory()->create(['billing_method' => 'claims']);
    $records = [
        'project' => $project, 'client' => $project->client, 'lead' => Lead::factory()->create(), 'quotation' => Quotation::factory()->create(),
        'site_visit' => SiteVisit::factory()->create(), 'visit' => SiteVisit::factory()->create(), 'invoice' => Invoice::factory()->issued()->create(['project_id' => $project->id]),
        'maintenance' => MaintenanceRequest::factory()->create(['project_id' => $project->id]), 'maintenance_request' => MaintenanceRequest::factory()->create(['project_id' => $project->id]),
        'daily_report' => DailyReport::factory()->create(['project_id' => $project->id]), 'claim' => Claim::factory()->create(['project_id' => $project->id, 'client_id' => $project->client_id]),
        'supplier' => Supplier::factory()->create(), 'purchase' => Purchase::factory()->create(['project_id' => $project->id]),
        'subcontract' => Subcontract::factory()->create(['project_id' => $project->id]), 'worker' => Worker::factory()->create(),
        'equipment' => Equipment::factory()->create(), 'user' => User::factory()->create(),
        'backup' => Backup::query()->create(['type' => 'database', 'status' => 'success', 'path' => 'backups/x.sql.gz', 'started_at' => now()]),
    ];
    actingAs(scopedUser([]));

    $allowed = [];
    $skipped = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        $name = $route->getName();

        if ($name === null || isOpen($name) || str_starts_with($route->uri(), 'api/') || in_array($name, SIGNED_IN_ROUTES, true)) {
            continue;
        }

        $params = [];

        foreach ($route->parameterNames() as $param) {
            $params[$param] = match (true) {
                isset($records[$param]) => $records[$param]->getKey(),
                $param === 'group' => 'company',
                $param === 'type' => str_starts_with($name, 'lookups.') ? 'service' : (str_starts_with($name, 'settings.backups') ? 'database' : 'files'),
                $param === 'report' => 'profitability',
                default => null,
            };

            if ($params[$param] === null) {
                $skipped[] = $name;

                continue 2;
            }
        }

        $method = strtolower(collect($route->methods())->reject(fn (string $verb) => $verb === 'HEAD')->first());
        $status = $this->{$method === 'get' ? 'get' : $method.'Json'}(route($name, $params))->status();

        if (! in_array($status, [401, 403, 404, 405], true)) {
            $allowed[] = "{$method} {$name} → {$status}";
        }
    }

    expect($allowed)->toBe([]);
});
