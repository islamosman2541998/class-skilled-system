<?php

use App\Enums\BillingMethod;
use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\DailyReport;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Services\Site\ReportCalendar;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\ProjectsDemoSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\SalesDemoSeeder;
use Database\Seeders\SiteDemoSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

it('seeds site demo data that renders every site page', function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([RolesSeeder::class, LookupSeeder::class, DocumentSequenceSeeder::class]);
    $admin = User::factory()->create()->assignRole('super-admin');
    User::factory()->count(3)->create()->each->assignRole('engineer');
    User::factory()->count(2)->create()->each->assignRole('sales');

    $this->seed([SalesDemoSeeder::class, ProjectsDemoSeeder::class, SiteDemoSeeder::class]);

    $claimsProject = Project::query()->whereNotNull('quotation_id')->where('billing_method', BillingMethod::Claims->value)->firstOrFail();

    expect(DailyReport::query()->count())->toBeGreaterThan(20)
        ->and(DailyReport::query()->where('has_obstacles', true)->exists())->toBeTrue()
        ->and(DailyReport::query()->has('stages')->exists())->toBeTrue()
        ->and(ReportCalendar::missingQuery($admin, today()->subDays(30), today()->subDay())->count())->toBeGreaterThan(0)
        ->and($claimsProject->contract_discount_percent)->not->toBe('0.0000')
        ->and($claimsProject->advance_amount)->not->toBe('0.00')
        ->and($claimsProject->installments()->count())->toBe(0)
        ->and($claimsProject->claims()->orderBy('id')->pluck('status')->map->value->all())->toBe(['invoiced', 'approved', 'submitted'])
        ->and(Claim::query()->where('status', ClaimStatus::Draft->value)->where('project_id', '!=', $claimsProject->id)->exists())->toBeTrue()
        ->and(Project::query()->where('billing_method', BillingMethod::Claims->value)->has('installments')->exists())->toBeFalse();

    actingAs($admin);
    $this->get(route('home'))->assertOk();
    $this->get(route('daily-reports.index'))->assertOk();
    $this->get(route('claims.index'))->assertOk();
    $this->get(route('reports.show', 'missing-reports'))->assertOk();

    foreach (DailyReport::query()->get() as $report) {
        $this->get(route('daily-reports.show', $report))->assertOk();
    }

    foreach (Claim::query()->get() as $claim) {
        $this->get(route('claims.show', $claim))->assertOk();
        $claim->isDraft()
            ? $this->get(route('claims.edit', $claim))->assertOk()
            : $this->get(route('claims.pdf', [$claim, 'lang' => 'both']))->assertOk();
    }

    foreach (['claims', 'daily_reports', 'invoices'] as $tab) {
        $this->get(route('projects.show', [$claimsProject, 'tab' => $tab]))->assertOk();
    }
});

it('gives the default roles their starting site permissions', function () {
    $this->seed(RolesSeeder::class);

    expect(Role::findByName('management')->hasPermissionTo('claims.approve'))->toBeTrue()
        ->and(Role::findByName('management')->hasPermissionTo('daily_reports.delete'))->toBeTrue()
        ->and(Role::findByName('engineer')->hasPermissionTo('daily_reports.create'))->toBeTrue()
        ->and(Role::findByName('engineer')->hasPermissionTo('claims.update'))->toBeTrue()
        ->and(Role::findByName('engineer')->hasPermissionTo('claims.approve'))->toBeFalse()
        ->and(Role::findByName('accountant')->hasPermissionTo('claims.print'))->toBeTrue()
        ->and(Role::findByName('accountant')->hasPermissionTo('claims.update'))->toBeFalse()
        ->and(Role::findByName('accountant')->hasPermissionTo('daily_reports.view'))->toBeTrue();
});
