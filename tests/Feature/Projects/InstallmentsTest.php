<?php

use App\Enums\InstallmentStatus;
use App\Models\Project;
use App\Models\User;
use App\Providers\ProjectsServiceProvider;
use App\Services\DueTasks;
use App\Services\InstallmentSchedule;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-15 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->user = scopedUser(['projects.view', 'projects.update']);
    $this->project = Project::factory()->create(['manager_id' => $this->user->id, 'original_value' => '1000.01', 'contract_value' => '1000.01']);
});

it('builds a schedule from percents with amounts that sum to the contract value', function () {
    InstallmentSchedule::fromPercents($this->project, [
        ['label_ar' => 'مقدم', 'percent' => '30'],
        ['label_ar' => 'منتصف', 'percent' => '40'],
        ['label_ar' => 'تسليم', 'percent' => '30'],
    ]);

    expect($this->project->installments()->pluck('amount')->all())->toBe(['300.00', '400.00', '300.01'])
        ->and(InstallmentSchedule::difference($this->project))->toBe('0.00');
});

it('derives amount from percent and the reverse', function () {
    actingAs($this->user)->post(route('projects.installments.store', $this->project), ['label_ar' => 'مقدم', 'percent' => '50', 'due_type' => 'date', 'due_date' => '2026-12-01'])->assertRedirect();
    actingAs($this->user)->post(route('projects.installments.store', $this->project), ['label_ar' => 'باقي', 'amount' => '250', 'due_type' => 'date', 'due_date' => '2027-01-01'])->assertRedirect();

    $rows = $this->project->installments()->get();
    expect($rows[0]->amount)->toBe('500.01')->and($rows[0]->percent)->toBe('50.00')
        ->and($rows[1]->amount)->toBe('250.00')->and($rows[1]->percent)->toBe('25.00');
});

it('warns when the schedule does not match the contract value', function () {
    $this->project->installments()->create(['label_ar' => 'مقدم', 'amount' => '400', 'due_type' => 'date', 'status' => 'not_due', 'sort_order' => 1]);

    expect(InstallmentSchedule::difference($this->project))->toBe('600.01');
    actingAs($this->user)->get(route('projects.show', [$this->project, 'tab' => 'payments']))->assertOk()
        ->assertSee(__('projects.installments.mismatch', ['amount' => money('600.01')]));
});

it('becomes due on its date and when its stage is done', function () {
    DueTasks::flush();
    ProjectsServiceProvider::registerDueTasks();
    $stage = $this->project->stages()->create(['name_ar' => 'الهيكل', 'weight' => '100', 'sort_order' => 1, 'status' => 'in_progress']);
    $byDate = $this->project->installments()->create(['label_ar' => 'بتاريخ', 'amount' => '100', 'due_type' => 'date', 'due_date' => '2026-11-15', 'status' => 'not_due', 'sort_order' => 1]);
    $byStage = $this->project->installments()->create(['label_ar' => 'بمرحلة', 'amount' => '100', 'due_type' => 'stage', 'stage_id' => $stage->id, 'status' => 'not_due', 'sort_order' => 2]);
    $later = $this->project->installments()->create(['label_ar' => 'لاحقًا', 'amount' => '100', 'due_type' => 'date', 'due_date' => '2027-01-01', 'status' => 'not_due', 'sort_order' => 3]);

    app(DueTasks::class)->runDue();
    expect($byDate->fresh()->status)->toBe(InstallmentStatus::Due)->and($byStage->fresh()->status)->toBe(InstallmentStatus::NotDue)
        ->and($later->fresh()->status)->toBe(InstallmentStatus::NotDue);

    actingAs($this->user)->put(route('projects.stages.update', [$this->project, $stage]), ['name_ar' => 'الهيكل', 'weight' => '100', 'status' => 'done']);
    expect($byStage->fresh()->status)->toBe(InstallmentStatus::Due);
});

it('notifies installment due once per installment', function () {
    DueTasks::flush();
    ProjectsServiceProvider::registerDueTasks();
    $accountant = User::factory()->create()->assignRole('accountant');
    $this->project->installments()->create(['label_ar' => 'قريبة', 'amount' => '100', 'due_type' => 'date', 'due_date' => '2026-11-17', 'status' => 'not_due', 'sort_order' => 1]);

    app(DueTasks::class)->runDue();
    $this->travel(1)->days();
    app(DueTasks::class)->runDue();

    expect($accountant->notifications()->where('type', 'installment_due')->count())->toBe(1);
});

it('refuses changes to invoiced installments', function () {
    $row = $this->project->installments()->create(['label_ar' => 'مفوترة', 'amount' => '100', 'due_type' => 'date', 'status' => 'invoiced', 'sort_order' => 1]);

    actingAs($this->user)->put(route('projects.installments.update', [$this->project, $row]), ['label_ar' => 'x', 'amount' => '1', 'due_type' => 'date'])
        ->assertSessionHas('error', __('projects.installments.locked'));
    actingAs($this->user)->delete(route('projects.installments.destroy', [$this->project, $row]))->assertSessionHas('error');

    expect($row->fresh()->label_ar)->toBe('مفوترة');
});

it('keeps installments of a hidden project out of reach', function () {
    $row = $this->project->installments()->create(['label_ar' => 'x', 'amount' => '100', 'due_type' => 'date', 'status' => 'not_due', 'sort_order' => 1]);
    $engineer = scopedUser(['projects.view', 'projects.update'], 'assigned');

    actingAs($engineer)->delete(route('projects.installments.destroy', [$this->project, $row]))->assertNotFound();
});
