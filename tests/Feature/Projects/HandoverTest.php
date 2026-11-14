<?php

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\ProjectHandover;
use App\Models\User;
use App\Providers\ProjectsServiceProvider;
use App\Services\DueTasks;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-15 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->user = scopedUser(['projects.view', 'projects.update', 'projects.print']);
    $this->project = Project::factory()->create(['manager_id' => $this->user->id, 'status' => 'completed', 'actual_end_date' => null]);
});

it('records a handover, delivers the project and computes warranty end', function () {
    actingAs($this->user)->put(route('projects.handover.update', $this->project), ['handover_date' => '2026-11-15', 'warranty_months' => '12', 'notes' => 'تم التسليم بحضور المالك'])->assertRedirect();

    $project = $this->project->fresh();
    $handover = $project->handover;

    expect($handover->warranty_end->toDateString())->toBe('2027-11-15')
        ->and($project->status)->toBe(ProjectStatus::Delivered)
        ->and($project->actual_end_date->toDateString())->toBe('2026-11-15');
});

it('month-end safe warranty end', function () {
    actingAs($this->user)->put(route('projects.handover.update', $this->project), ['handover_date' => '2027-01-31', 'warranty_months' => '1']);

    expect($this->project->fresh()->handover->warranty_end->toDateString())->toBe('2027-02-28');
});

it('updates an existing handover instead of adding another', function () {
    actingAs($this->user)->put(route('projects.handover.update', $this->project), ['handover_date' => '2026-11-15', 'warranty_months' => '12']);
    actingAs($this->user)->put(route('projects.handover.update', $this->project), ['handover_date' => '2026-11-16', 'warranty_months' => '24']);

    expect(ProjectHandover::query()->count())->toBe(1)
        ->and($this->project->fresh()->handover->warranty_end->toDateString())->toBe('2028-11-16');
});

it('renders the handover PDF in three languages and logs printing', function (string $lang) {
    actingAs($this->user)->put(route('projects.handover.update', $this->project), ['handover_date' => '2026-11-15', 'warranty_months' => '12']);

    $response = actingAs($this->user)->get(route('projects.handover.pdf', [$this->project, 'lang' => $lang]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and(Activity::query()->where('event', 'printed')->where('subject_id', $this->project->id)->count())->toBe(1);
})->with(['ar', 'en', 'both']);

it('notifies project ending and warranty ending once', function () {
    DueTasks::flush();
    ProjectsServiceProvider::registerDueTasks();
    $ending = Project::factory()->create(['manager_id' => $this->user->id, 'status' => 'in_progress', 'planned_end_date' => '2026-11-20']);
    $this->project->handover()->create(['handover_date' => '2025-12-01', 'warranty_months' => 12, 'warranty_end' => '2026-12-01', 'created_by' => $this->user->id]);
    $management = User::factory()->create()->assignRole('management');

    app(DueTasks::class)->runDue();
    $this->travel(1)->days();
    app(DueTasks::class)->runDue();

    expect($this->user->notifications()->where('type', 'project_ending')->count())->toBe(1)
        ->and($management->notifications()->where('type', 'warranty_ending')->count())->toBe(1);
});

it('needs projects.update and visibility', function () {
    $engineer = scopedUser(['projects.view', 'projects.update'], 'assigned');

    actingAs(scopedUser(['projects.view']))->put(route('projects.handover.update', $this->project), ['handover_date' => '2026-11-15', 'warranty_months' => '12'])->assertForbidden();
    actingAs($engineer)->put(route('projects.handover.update', $this->project), ['handover_date' => '2026-11-15', 'warranty_months' => '12'])->assertNotFound();
});

it('shows the handover tab', function () {
    actingAs($this->user)->get(route('projects.show', [$this->project, 'tab' => 'handover']))->assertOk()->assertSee(__('projects.handover.title'));
});
