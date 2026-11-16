<?php

use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\Project;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['projects.view', 'equipment.view', 'equipment.create', 'equipment.update', 'equipment.delete']);
    $this->project = Project::factory()->create();
    $this->crane = Equipment::factory()->create(['name_ar' => 'رافعة 25 طن', 'ownership' => 'rented', 'daily_cost' => '350']);
});

it('assigns rented equipment for 10 days and the project cost rises by 10 × daily cost', function () {
    actingAs($this->user)->post(route('equipment.assignments.store', $this->crane), [
        'project_id' => $this->project->id, 'date_from' => '2026-11-01', 'date_to' => '2026-11-10',
    ])->assertRedirect();

    $assignment = EquipmentAssignment::query()->sole();

    expect($assignment->days)->toBe(10)
        ->and($assignment->daily_cost)->toBe('350.00')
        ->and($assignment->cost)->toBe('3500.00')
        ->and($this->project->fresh()->cost_total)->toBe('3500.00');

    actingAs($this->user)->get(route('equipment.show', $this->crane))->assertOk()->assertSee($this->project->number);
});

it('refuses an overlapping assignment of the same equipment', function () {
    $other = Project::factory()->create();
    actingAs($this->user)->post(route('equipment.assignments.store', $this->crane), ['project_id' => $this->project->id, 'date_from' => '2026-11-01', 'date_to' => '2026-11-10']);

    actingAs($this->user)->post(route('equipment.assignments.store', $this->crane), ['project_id' => $other->id, 'date_from' => '2026-11-10', 'date_to' => '2026-11-15'])
        ->assertSessionHasErrors('date_from');
    actingAs($this->user)->post(route('equipment.assignments.store', $this->crane), ['project_id' => $other->id, 'date_from' => '2026-11-11', 'date_to' => '2026-11-15'])
        ->assertSessionHasNoErrors();

    expect(EquipmentAssignment::query()->count())->toBe(2);
});

it('editing and deleting an assignment moves its cost', function () {
    actingAs($this->user)->post(route('equipment.assignments.store', $this->crane), ['project_id' => $this->project->id, 'date_from' => '2026-11-01', 'date_to' => '2026-11-10']);
    $assignment = EquipmentAssignment::query()->sole();

    actingAs($this->user)->put(route('equipment-assignments.update', $assignment), ['project_id' => $this->project->id, 'date_from' => '2026-11-01', 'date_to' => '2026-11-04', 'daily_cost' => '300'])
        ->assertSessionHasNoErrors();
    expect($assignment->fresh()->cost)->toBe('1200.00')->and($this->project->fresh()->cost_total)->toBe('1200.00');

    actingAs($this->user)->delete(route('equipment-assignments.destroy', $assignment))->assertRedirect();
    $this->assertSoftDeleted($assignment);
    expect($this->project->fresh()->cost_total)->toBe('0.00');
});

it('creates equipment and refuses a date to before the date from', function () {
    actingAs($this->user)->post(route('equipment.store'), ['name_ar' => 'خلاطة', 'ownership' => 'owned', 'daily_cost' => '80'])->assertRedirect();
    expect(Equipment::query()->where('name_ar', 'خلاطة')->exists())->toBeTrue();

    actingAs($this->user)->post(route('equipment.assignments.store', $this->crane), ['project_id' => $this->project->id, 'date_from' => '2026-11-10', 'date_to' => '2026-11-01'])
        ->assertSessionHasErrors('date_to');
});
