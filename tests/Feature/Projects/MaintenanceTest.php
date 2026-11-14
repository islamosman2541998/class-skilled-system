<?php

use App\Enums\MaintenanceStatus;
use App\Livewire\Tables\MaintenanceTable;
use App\Models\MaintenanceRequest;
use App\Models\Project;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-15 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->user = scopedUser(['maintenance.view', 'maintenance.create', 'maintenance.update', 'projects.view']);
    $this->project = Project::factory()->create(['status' => 'delivered']);
    $this->project->handover()->create(['handover_date' => '2026-01-10', 'warranty_months' => 12, 'warranty_end' => '2027-01-10', 'created_by' => $this->user->id]);
});

it('creates a request with number MR-2026-0001 inside warranty', function () {
    $engineer = scopedUser(['maintenance.view'], 'assigned');

    actingAs($this->user)->post(route('maintenance.store'), [
        'project_id' => $this->project->id,
        'request_date' => '2026-11-15',
        'description' => 'تسريب مياه في السطح',
        'assigned_to' => $engineer->id,
    ])->assertRedirect();

    $request = MaintenanceRequest::query()->firstOrFail();

    expect($request->number)->toBe('MR-2026-0001')
        ->and($request->in_warranty)->toBeTrue()
        ->and($request->status)->toBe(MaintenanceStatus::Open)
        ->and($request->created_by)->toBe($this->user->id)
        ->and($engineer->notifications()->where('type', 'assigned')->count())->toBe(1);
});

it('outside warranty after the warranty end', function () {
    actingAs($this->user)->post(route('maintenance.store'), [
        'project_id' => $this->project->id,
        'request_date' => '2027-01-11',
        'description' => 'تشقق في طبقة الحماية',
    ])->assertRedirect();

    expect(MaintenanceRequest::query()->firstOrFail()->in_warranty)->toBeFalse();
});

it('without a handover it is outside warranty', function () {
    $project = Project::factory()->create();

    actingAs($this->user)->post(route('maintenance.store'), [
        'project_id' => $project->id,
        'request_date' => '2026-11-15',
        'description' => 'فحص',
    ])->assertRedirect();

    expect(MaintenanceRequest::query()->firstOrFail()->in_warranty)->toBeFalse();
});

it('changing the warranty flag needs a reason in notes', function () {
    $request = MaintenanceRequest::factory()->create(['project_id' => $this->project->id, 'request_date' => '2026-11-15', 'in_warranty' => true]);
    $payload = ['project_id' => $this->project->id, 'request_date' => '2026-11-15', 'description' => 'تسريب', 'status' => 'in_progress', 'cost' => '0', 'in_warranty' => '0'];

    actingAs($this->user)->put(route('maintenance.update', $request), $payload)->assertSessionHasErrors('notes');
    actingAs($this->user)->put(route('maintenance.update', $request), $payload + ['notes' => 'سوء استخدام من المالك'])->assertSessionHasNoErrors();

    expect($request->fresh()->in_warranty)->toBeFalse()
        ->and($request->fresh()->status)->toBe(MaintenanceStatus::InProgress);
});

it('assigned user and project team see it, others get 404', function () {
    $assignee = scopedUser(['maintenance.view'], 'assigned');
    $manager = scopedUser(['maintenance.view'], 'assigned');
    $member = scopedUser(['maintenance.view'], 'assigned');
    $stranger = scopedUser(['maintenance.view'], 'assigned');
    $project = Project::factory()->create(['manager_id' => $manager->id]);
    $project->members()->attach($member->id);
    $request = MaintenanceRequest::factory()->create(['project_id' => $project->id]);
    $assigned = MaintenanceRequest::factory()->create(['assigned_to' => $assignee->id]);

    actingAs($manager)->get(route('maintenance.show', $request))->assertOk();
    actingAs($member)->get(route('maintenance.show', $request))->assertOk();
    actingAs($assignee)->get(route('maintenance.show', $assigned))->assertOk();
    actingAs($assignee)->get(route('maintenance.show', $request))->assertNotFound();
    actingAs($stranger)->get(route('maintenance.show', $request))->assertNotFound();

    actingAs($stranger);
    expect(Livewire::test(MaintenanceTable::class)->viewData('rows')->total())->toBe(0);
    actingAs($member);
    expect(Livewire::test(MaintenanceTable::class)->viewData('rows')->total())->toBe(1);
});

it('cannot open a request on a project the user does not see', function () {
    $engineer = scopedUser(['maintenance.view', 'maintenance.create'], 'assigned');

    actingAs($engineer)->post(route('maintenance.store'), [
        'project_id' => $this->project->id,
        'request_date' => '2026-11-15',
        'description' => 'فحص',
    ])->assertSessionHasErrors('project_id');
});

it('filters by in warranty and sums cost', function () {
    MaintenanceRequest::factory()->create(['in_warranty' => true, 'cost' => '0']);
    MaintenanceRequest::factory()->create(['in_warranty' => false, 'cost' => '1500.50']);
    MaintenanceRequest::factory()->create(['in_warranty' => false, 'cost' => '500']);

    actingAs($this->user);

    expect(Livewire::test(MaintenanceTable::class)->set('filters.in_warranty', '0')->viewData('rows')->total())->toBe(2)
        ->and(Livewire::test(MaintenanceTable::class)->viewData('totals')['cost'])->toBe('2,000.50');
});

it('lists the requests of a project on its handover tab', function () {
    MaintenanceRequest::factory()->create(['project_id' => $this->project->id, 'number' => 'MR-2026-0042']);
    $viewer = scopedUser(['projects.view', 'maintenance.view']);

    actingAs($viewer)->get(route('projects.show', [$this->project, 'tab' => 'handover']))->assertOk()->assertSee('MR-2026-0042');
});

it('needs maintenance permissions', function () {
    actingAs(scopedUser(['maintenance.view']))->get(route('maintenance.create'))->assertForbidden();
    actingAs(scopedUser([]))->get(route('maintenance.index'))->assertForbidden();
});
