<?php

use App\Enums\ProjectStatus;
use App\Livewire\Tables\ProjectsTable;
use App\Models\Client;
use App\Models\Project;
use App\Settings\FinanceSettings;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-15 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['projects.view', 'projects.create', 'projects.update', 'clients.view']);
});

function projectPayload(array $overrides = []): array
{
    return array_merge([
        'client_id' => Client::factory()->create()->id,
        'title_ar' => 'فيلا الشامخة',
        'title_en' => 'Al Shamkha villa',
        'original_value' => '385000',
        'billing_method' => 'installments',
        'start_date' => '2026-11-20',
        'planned_end_date' => '2027-03-20',
        'manager_id' => auth()->id(),
    ], $overrides);
}

it('creates a project directly with number PRJ-2026-0001, VAT from settings and contract value equal to the original', function () {
    actingAs($this->user)->post(route('projects.store'), projectPayload(['manager_id' => $this->user->id]))->assertRedirect();

    $project = Project::query()->firstOrFail();

    expect($project->number)->toBe('PRJ-2026-0001')
        ->and($project->status)->toBe(ProjectStatus::NotStarted)
        ->and($project->vat_rate)->toBe(app(FinanceSettings::class)->vat_rate)
        ->and($project->original_value)->toBe('385000.00')
        ->and($project->contract_value)->toBe('385000.00')
        ->and($project->progress)->toBe('0.00');
});

it('ignores a posted contract_value', function () {
    actingAs($this->user)->post(route('projects.store'), projectPayload(['manager_id' => $this->user->id, 'contract_value' => '1']));

    expect(Project::query()->value('contract_value'))->toBe('385000.00');
});

it('notifies the manager and new team members, not the actor', function () {
    $manager = scopedUser(['projects.view']);
    $member = scopedUser(['projects.view']);

    actingAs($this->user)->post(route('projects.store'), projectPayload(['manager_id' => $manager->id, 'members' => [$member->id, $this->user->id]]));

    expect($manager->notifications()->count())->toBe(1)
        ->and($member->notifications()->count())->toBe(1)
        ->and($this->user->notifications()->count())->toBe(0);

    $project = Project::query()->firstOrFail();
    actingAs($this->user)->put(route('projects.update', $project), projectPayload(['client_id' => $project->client_id, 'manager_id' => $manager->id, 'members' => [$member->id], 'status' => 'in_progress']));

    expect($member->notifications()->count())->toBe(1);
});

it('computes days left and late', function () {
    $onTime = Project::factory()->create(['planned_end_date' => '2026-11-25', 'status' => 'in_progress']);
    $late = Project::factory()->create(['planned_end_date' => '2026-11-10', 'status' => 'in_progress']);
    $done = Project::factory()->create(['planned_end_date' => '2026-11-10', 'status' => 'delivered']);

    expect($onTime->daysLeft())->toBe(10)->and($onTime->isLate())->toBeFalse()
        ->and($late->daysLeft())->toBe(-5)->and($late->isLate())->toBeTrue()
        ->and($done->isLate())->toBeFalse()
        ->and(Project::factory()->create(['planned_end_date' => null])->daysLeft())->toBeNull();
});

it('filters late projects and sums contract value', function () {
    Project::factory()->create(['planned_end_date' => '2026-11-10', 'status' => 'in_progress', 'contract_value' => '100000']);
    Project::factory()->create(['planned_end_date' => '2026-12-10', 'status' => 'in_progress', 'contract_value' => '50000']);
    actingAs($this->user);

    expect(Livewire::test(ProjectsTable::class)->set('filters.late', '1')->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(ProjectsTable::class)->viewData('totals')['contract_value'])->toBe('150,000.00');
});

it('blocks deleting a client that has a project', function () {
    $project = Project::factory()->create();

    expect($project->client->deletionBlockedReason(scopedUser()))->toBe(__('clients.has_records'));
});

it('shows the project page with its tabs', function () {
    $project = Project::factory()->create(['title_ar' => 'مستودع الخليج', 'manager_id' => $this->user->id]);

    actingAs($this->user)->get(route('projects.show', $project))->assertOk()
        ->assertSee('مستودع الخليج')->assertSee(__('projects.tabs.overview'))->assertSee(__('uploads.attachments'));
});

it('requires a title, client, manager and a non-negative value', function () {
    actingAs($this->user)->post(route('projects.store'), projectPayload(['title_ar' => '', 'original_value' => '-1', 'manager_id' => null]))
        ->assertSessionHasErrors(['title_ar', 'original_value', 'manager_id']);
});
