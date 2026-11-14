<?php

use App\Enums\LookupType;
use App\Enums\ProjectStatus;
use App\Enums\StageStatus;
use App\Models\Client;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\StageTemplate;
use App\Models\User;
use App\Providers\ProjectsServiceProvider;
use App\Services\DueTasks;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-15 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->user = scopedUser(['projects.view', 'projects.create', 'projects.update']);
    $this->project = Project::factory()->create(['manager_id' => $this->user->id]);
});

it('copies the service stage template on creation', function () {
    $service = Lookup::query()->ofType(LookupType::Service)->firstOrFail();
    StageTemplate::query()->create(['service_id' => $service->id, 'name_ar' => 'الحفر', 'weight' => '20', 'sort_order' => 1]);
    StageTemplate::query()->create(['service_id' => $service->id, 'name_ar' => 'الهيكل', 'weight' => '80', 'sort_order' => 2]);

    actingAs($this->user)->post(route('projects.store'), [
        'client_id' => Client::factory()->create()->id, 'title_ar' => 'مشروع', 'original_value' => '1000',
        'billing_method' => 'installments', 'manager_id' => $this->user->id, 'service_id' => $service->id,
    ])->assertRedirect();

    $project = Project::query()->latest('id')->firstOrFail();
    expect($project->stages()->pluck('name_ar')->all())->toBe(['الحفر', 'الهيكل'])
        ->and($project->stages()->first()->weight)->toBe('20.00');
});

it('refreshes project progress after a stage update', function () {
    $a = $this->project->stages()->create(['name_ar' => 'أ', 'weight' => '25', 'sort_order' => 1, 'status' => 'not_started']);
    $this->project->stages()->create(['name_ar' => 'ب', 'weight' => '75', 'sort_order' => 2, 'status' => 'not_started']);

    actingAs($this->user)->put(route('projects.stages.update', [$this->project, $a]), ['name_ar' => 'أ', 'weight' => '25', 'progress' => '100'])->assertRedirect();

    expect($this->project->fresh()->progress)->toBe('25.00');
});

it('moves a not started project to in progress when a stage starts', function () {
    $this->project->update(['status' => 'not_started']);
    $stage = $this->project->stages()->create(['name_ar' => 'أ', 'weight' => '100', 'sort_order' => 1, 'status' => 'not_started']);

    actingAs($this->user)->put(route('projects.stages.update', [$this->project, $stage]), ['name_ar' => 'أ', 'weight' => '100', 'progress' => '10']);

    expect($this->project->fresh()->status)->toBe(ProjectStatus::InProgress);
});

it('done means 100 and the reverse, and progress sets actual dates', function () {
    $stage = $this->project->stages()->create(['name_ar' => 'أ', 'weight' => '100', 'sort_order' => 1, 'status' => 'not_started']);

    actingAs($this->user)->put(route('projects.stages.update', [$this->project, $stage]), ['name_ar' => 'أ', 'weight' => '100', 'progress' => '40']);
    $stage->refresh();
    expect($stage->status)->toBe(StageStatus::InProgress)->and($stage->actual_start?->toDateString())->toBe('2026-11-15');

    actingAs($this->user)->put(route('projects.stages.update', [$this->project, $stage]), ['name_ar' => 'أ', 'weight' => '100', 'progress' => '40', 'status' => 'done']);
    $stage->refresh();
    expect($stage->progress)->toBe('100.00')->and($stage->actual_end?->toDateString())->toBe('2026-11-15');
});

it('adds, reorders and deletes stages', function () {
    actingAs($this->user)->post(route('projects.stages.store', $this->project), ['name_ar' => 'مرحلة 1', 'weight' => '50'])->assertRedirect();
    actingAs($this->user)->post(route('projects.stages.store', $this->project), ['name_ar' => 'مرحلة 2', 'weight' => '50'])->assertRedirect();
    [$first, $second] = $this->project->stages()->get()->all();

    actingAs($this->user)->postJson(route('projects.stages.reorder', $this->project), ['ids' => [$second->id, $first->id]])->assertOk();
    expect($this->project->stages()->pluck('name_ar')->all())->toBe(['مرحلة 2', 'مرحلة 1']);

    actingAs($this->user)->delete(route('projects.stages.destroy', [$this->project, $first]))->assertRedirect();
    expect($this->project->stages()->count())->toBe(1)->and(ProjectStage::withTrashed()->count())->toBe(2);
});

it('flags late stages and notifies once', function () {
    DueTasks::flush();
    ProjectsServiceProvider::registerDueTasks();
    $stage = $this->project->stages()->create(['name_ar' => 'متأخرة', 'weight' => '100', 'sort_order' => 1, 'status' => 'in_progress', 'planned_end' => '2026-11-10']);
    $management = User::factory()->create()->assignRole('management');

    expect($stage->isLate())->toBeTrue();

    app(DueTasks::class)->runDue();
    $this->travel(2)->days();
    app(DueTasks::class)->runDue();

    expect($this->user->notifications()->where('type', 'stage_late')->count())->toBe(1)
        ->and($management->notifications()->where('type', 'stage_late')->count())->toBe(1);
});

it('lets nobody outside the project change its stages', function () {
    $stage = $this->project->stages()->create(['name_ar' => 'أ', 'weight' => '100', 'sort_order' => 1, 'status' => 'not_started']);
    $engineer = scopedUser(['projects.view', 'projects.update'], 'assigned');

    actingAs($engineer)->put(route('projects.stages.update', [$this->project, $stage]), ['name_ar' => 'x', 'weight' => '1', 'progress' => '100'])->assertNotFound();
    expect($stage->fresh()->name_ar)->toBe('أ');
});

it('shows the stages tab with the timeline', function () {
    $this->project->stages()->create(['name_ar' => 'مرحلة العزل', 'weight' => '100', 'sort_order' => 1, 'status' => 'not_started', 'planned_start' => '2026-11-20', 'planned_end' => '2026-12-20']);

    actingAs($this->user)->get(route('projects.show', [$this->project, 'tab' => 'stages']))->assertOk()->assertSee('مرحلة العزل')->assertSee('timeline', false);
});

it('loads the stages script through Vite, not a bare module import', function () {
    $response = actingAs($this->user)->get(route('projects.show', [$this->project, 'tab' => 'stages']))->assertOk();

    $response->assertDontSee("import('sortablejs')", false);
});
