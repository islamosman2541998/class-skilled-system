<?php

use App\Enums\ProjectStatus;
use App\Models\MaintenanceRequest;
use App\Models\Project;
use App\Models\ProjectHandover;
use App\Models\Role;
use App\Models\User;
use App\Models\Variation;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\ProjectsDemoSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SalesDemoSeeder;

use function Pest\Laravel\actingAs;

it('seeds projects demo data that renders every projects page', function () {
    $this->seed([RolesSeeder::class, LookupSeeder::class, DocumentSequenceSeeder::class]);
    $admin = User::factory()->create()->assignRole('super-admin');
    User::factory()->count(3)->create()->each->assignRole('engineer');
    User::factory()->count(2)->create()->each->assignRole('sales');

    $this->seed([SalesDemoSeeder::class, ProjectsDemoSeeder::class]);

    $statuses = Project::query()->pluck('status')->map(fn (ProjectStatus $status) => $status->value)->unique();

    expect(Project::query()->count())->toBeGreaterThanOrEqual(12)
        ->and(Project::query()->whereNotNull('quotation_id')->exists())->toBeTrue()
        ->and($statuses->sort()->values()->all())->toBe(collect(ProjectStatus::cases())->map->value->sort()->values()->all())
        ->and(Project::query()->get()->filter->isLate()->count())->toBeGreaterThanOrEqual(2)
        ->and(Project::query()->doesntHave('stages')->exists())->toBeFalse()
        ->and(Project::query()->doesntHave('installments')->exists())->toBeFalse()
        ->and(Variation::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(ProjectHandover::query()->count())->toBe(3)
        ->and(MaintenanceRequest::query()->count())->toBe(10)
        ->and(MaintenanceRequest::query()->where('in_warranty', true)->exists())->toBeTrue()
        ->and(MaintenanceRequest::query()->where('in_warranty', false)->exists())->toBeTrue();

    actingAs($admin);
    $this->get(route('projects.index'))->assertOk();
    $this->get(route('maintenance.index'))->assertOk();
    $this->get(route('settings.stage-templates'))->assertOk();

    $delivered = Project::query()->has('handover')->firstOrFail();
    foreach (['overview', 'stages', 'payments', 'variations', 'handover', 'attachments', 'activity'] as $tab) {
        $this->get(route('projects.show', [$delivered, 'tab' => $tab]))->assertOk();
    }

    $this->get(route('projects.handover.pdf', [$delivered, 'lang' => 'both']))->assertOk();
    $this->get(route('maintenance.show', MaintenanceRequest::query()->first()))->assertOk();

    $variation = Variation::query()->firstOrFail();
    $this->get(route('projects.variations.show', [$variation->project_id, $variation]))->assertOk();
});

it('gives the default roles their starting projects permissions', function () {
    $this->seed(RolesSeeder::class);

    expect(Role::findByName('management')->hasPermissionTo('projects.approve'))->toBeTrue()
        ->and(Role::findByName('management')->hasPermissionTo('maintenance.delete'))->toBeTrue()
        ->and(Role::findByName('engineer')->hasPermissionTo('projects.update'))->toBeTrue()
        ->and(Role::findByName('engineer')->hasPermissionTo('projects.create'))->toBeFalse()
        ->and(Role::findByName('engineer')->hasPermissionTo('maintenance.create'))->toBeTrue()
        ->and(Role::findByName('accountant')->hasPermissionTo('projects.print'))->toBeTrue()
        ->and(Role::findByName('accountant')->hasPermissionTo('maintenance.view'))->toBeFalse();
});
