<?php

use App\Enums\AttendanceStatus;
use App\Enums\LookupType;
use App\Livewire\Tables\AttendanceTable;
use App\Models\Attendance;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Worker;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->engineer = scopedUser(['projects.view', 'workers.view', 'attendance.view', 'attendance.create', 'attendance.update'], 'assigned');
    $this->project = Project::factory()->create(['manager_id' => $this->engineer->id, 'status' => 'in_progress']);
    $this->workers = Worker::factory()->count(5)->create(['daily_wage' => '150']);
});

/** Sheet rows: worker id => [status, overtime]. */
function sheet(array $rows): array
{
    return collect($rows)->mapWithKeys(fn (array $row, int $id) => [$id => ['status' => $row[0], 'overtime_hours' => $row[1] ?? '0']])->all();
}

it('engineer records attendance for five workers on own project and the project cost rises', function () {
    [$a, $b, $c, $d, $e] = $this->workers->pluck('id')->all();

    actingAs($this->engineer)->get(route('attendance.sheet', ['project' => $this->project->id, 'date' => '2026-11-15']))->assertOk();
    actingAs($this->engineer)->post(route('attendance.store'), [
        'project_id' => $this->project->id, 'work_date' => '2026-11-15',
        'rows' => sheet([$a => ['present'], $b => ['present'], $c => ['present'], $d => ['half'], $e => ['absent', '2']]),
    ])->assertRedirect();

    expect(Attendance::query()->count())->toBe(5)
        ->and(Attendance::query()->where('worker_id', $e)->value('cost'))->toBe('46.88')
        ->and(Attendance::query()->where('worker_id', $d)->first()->status)->toBe(AttendanceStatus::Half)
        ->and($this->project->fresh()->cost_total)->toBe('571.88');
});

it('saving the same sheet twice keeps one row per worker', function () {
    $rows = sheet($this->workers->mapWithKeys(fn (Worker $worker) => [$worker->id => ['present']])->all());

    foreach ([1, 2] as $time) {
        actingAs($this->engineer)->post(route('attendance.store'), ['project_id' => $this->project->id, 'work_date' => '2026-11-15', 'rows' => $rows])->assertRedirect();
    }

    expect(Attendance::query()->count())->toBe(5)->and($this->project->fresh()->cost_total)->toBe('750.00');
});

it('refuses a worker already recorded on another project that day', function () {
    $other = Project::factory()->create(['manager_id' => $this->engineer->id]);
    $worker = $this->workers->first();
    actingAs($this->engineer)->post(route('attendance.store'), ['project_id' => $other->id, 'work_date' => '2026-11-15', 'rows' => sheet([$worker->id => ['present']])]);

    actingAs($this->engineer)->post(route('attendance.store'), ['project_id' => $this->project->id, 'work_date' => '2026-11-15', 'rows' => sheet([$worker->id => ['present']])])
        ->assertSessionHasErrors("rows.{$worker->id}");

    expect(Attendance::query()->count())->toBe(1)->and(Attendance::query()->value('project_id'))->toBe($other->id);
});

it('blank status removes the row', function () {
    $worker = $this->workers->first();
    actingAs($this->engineer)->post(route('attendance.store'), ['project_id' => $this->project->id, 'work_date' => '2026-11-15', 'rows' => sheet([$worker->id => ['present']])]);
    actingAs($this->engineer)->post(route('attendance.store'), ['project_id' => $this->project->id, 'work_date' => '2026-11-15', 'rows' => sheet([$worker->id => ['']])]);

    expect(Attendance::query()->count())->toBe(0)->and($this->project->fresh()->cost_total)->toBe('0.00');
});

it('a later wage change does not change past cost', function () {
    $worker = $this->workers->first();
    actingAs($this->engineer)->post(route('attendance.store'), ['project_id' => $this->project->id, 'work_date' => '2026-11-15', 'rows' => sheet([$worker->id => ['present']])]);
    $worker->update(['daily_wage' => '300']);

    actingAs($this->engineer)->post(route('attendance.store'), ['project_id' => $this->project->id, 'work_date' => '2026-11-15', 'rows' => sheet([$worker->id => ['present']])]);
    expect(Attendance::query()->value('cost'))->toBe('150.00');

    actingAs($this->engineer)->post(route('attendance.store'), ['project_id' => $this->project->id, 'work_date' => '2026-11-16', 'rows' => sheet([$worker->id => ['present']])]);
    expect(Attendance::query()->whereDate('work_date', '2026-11-16')->value('cost'))->toBe('300.00');
});

it('engineer cannot record on another engineer project', function () {
    $foreign = Project::factory()->create();

    actingAs($this->engineer)->get(route('attendance.sheet', ['project' => $foreign->id, 'date' => '2026-11-15']))->assertNotFound();
    actingAs($this->engineer)->post(route('attendance.store'), ['project_id' => $foreign->id, 'work_date' => '2026-11-15', 'rows' => sheet([$this->workers->first()->id => ['present']])])
        ->assertNotFound();
});

it('attendance list filters by project, worker, trade and date', function () {
    $trade = Lookup::query()->ofType(LookupType::WorkerTrade)->value('id');
    $mason = Worker::factory()->create(['trade_id' => $trade]);
    $other = Project::factory()->create(['manager_id' => $this->engineer->id]);
    actingAs($this->engineer)->post(route('attendance.store'), ['project_id' => $this->project->id, 'work_date' => '2026-11-14', 'rows' => sheet([$mason->id => ['present'], $this->workers[0]->id => ['present']])]);
    actingAs($this->engineer)->post(route('attendance.store'), ['project_id' => $other->id, 'work_date' => '2026-11-15', 'rows' => sheet([$this->workers[1]->id => ['half']])]);

    expect(Livewire::test(AttendanceTable::class)->viewData('rows')->total())->toBe(3)
        ->and(Livewire::test(AttendanceTable::class)->set('filters.project_id', (string) $other->id)->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(AttendanceTable::class)->set('filters.worker_id', (string) $mason->id)->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(AttendanceTable::class)->set('filters.trade_id', (string) $trade)->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(AttendanceTable::class)->call('setDateRange', '2026-11-15', '2026-11-15')->viewData('rows')->total())->toBe(1);
});
