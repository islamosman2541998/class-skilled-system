<?php

use App\Enums\StageStatus;
use App\Livewire\Tables\DailyReportsTable;
use App\Models\DailyReport;
use App\Models\Project;
use App\Models\User;
use App\Services\Uploads\TemporaryUpload;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function siteUpload(User $owner, string $name, string $bytes): TemporaryUpload
{
    $upload = TemporaryUpload::create($owner->id, strlen($bytes), $name);
    file_put_contents($upload->path(), $bytes);
    $upload->saveMeta(['offset' => strlen($bytes)]);

    return $upload;
}

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->engineer = scopedUser(['projects.view', 'daily_reports.view', 'daily_reports.create', 'daily_reports.update', 'daily_reports.delete'], 'assigned');
    $this->project = Project::factory()->create(['manager_id' => $this->engineer->id, 'start_date' => '2026-11-01']);
    $this->stageA = $this->project->stages()->create(['name_ar' => 'الحفر', 'sort_order' => 1, 'weight' => '50', 'status' => 'not_started', 'progress' => '0']);
    $this->stageB = $this->project->stages()->create(['name_ar' => 'الأساسات', 'sort_order' => 2, 'weight' => '50', 'status' => 'not_started', 'progress' => '0']);
    $this->payload = fn (array $extra = []) => [
        'project_id' => $this->project->id,
        'report_date' => '2026-11-16',
        'work_done' => 'صب خرسانة القواعد',
        'labour_count' => 8,
        'stages' => [$this->stageA->id => '100', $this->stageB->id => '40'],
        ...$extra,
    ];
});

it('engineer saves a report for own project with two stages and the project progress follows', function () {
    actingAs($this->engineer)->get(route('daily-reports.create', ['project' => $this->project->id]))->assertOk();
    actingAs($this->engineer)->post(route('daily-reports.store'), ($this->payload)(['obstacles' => 'تأخر توريد الحديد']))
        ->assertRedirect();

    $report = DailyReport::query()->firstOrFail();
    $this->stageA->refresh();
    $this->stageB->refresh();

    expect($report->engineer_id)->toBe($this->engineer->id)
        ->and($report->has_obstacles)->toBeTrue()
        ->and($report->stages()->count())->toBe(2)
        ->and($this->stageA->status)->toBe(StageStatus::Done)
        ->and($this->stageA->actual_end->toDateString())->toBe('2026-11-16')
        ->and((string) $this->stageB->progress)->toBe('40.00')
        ->and((string) $this->project->refresh()->progress)->toBe('70.00');

    actingAs($this->engineer)->get(route('daily-reports.show', $report))->assertOk()->assertSee('صب خرسانة القواعد');
});

it('refuses a second report for the same project and day and links the first', function () {
    actingAs($this->engineer)->post(route('daily-reports.store'), ($this->payload)());
    $first = DailyReport::query()->firstOrFail();

    $other = scopedUser(['projects.view', 'daily_reports.view', 'daily_reports.create'], 'assigned');
    $this->project->members()->attach($other->id);

    actingAs($other)->post(route('daily-reports.store'), ($this->payload)(['work_done' => 'تقرير ثان']))
        ->assertSessionHasErrors('report_date');

    expect(DailyReport::query()->count())->toBe(1)
        ->and(session('errors')->first('report_date'))->toContain(route('daily-reports.show', $first));
});

it('refuses a future date', function () {
    actingAs($this->engineer)->post(route('daily-reports.store'), ($this->payload)(['report_date' => '2026-11-17']))
        ->assertSessionHasErrors('report_date');

    expect(DailyReport::query()->count())->toBe(0);
});

it('an older report saved later does not lower a stage set by a newer report', function () {
    actingAs($this->engineer)->post(route('daily-reports.store'), ($this->payload)(['stages' => [$this->stageB->id => '60']]));
    actingAs($this->engineer)->post(route('daily-reports.store'), ($this->payload)(['report_date' => '2026-11-12', 'stages' => [$this->stageB->id => '30']]));

    expect(DailyReport::query()->count())->toBe(2)
        ->and((string) $this->stageB->refresh()->progress)->toBe('60.00');

    // Editing the newer report moves the stage; editing the older one does not.
    $newer = DailyReport::query()->whereDate('report_date', '2026-11-16')->firstOrFail();
    $older = DailyReport::query()->whereDate('report_date', '2026-11-12')->firstOrFail();
    actingAs($this->engineer)->put(route('daily-reports.update', $older), ($this->payload)(['report_date' => '2026-11-12', 'stages' => [$this->stageB->id => '90']]));
    expect((string) $this->stageB->refresh()->progress)->toBe('60.00');

    actingAs($this->engineer)->put(route('daily-reports.update', $newer), ($this->payload)(['stages' => [$this->stageB->id => '75']]));
    expect((string) $this->stageB->refresh()->progress)->toBe('75.00');
});

it('deleting a report keeps stage progress', function () {
    actingAs($this->engineer)->post(route('daily-reports.store'), ($this->payload)());
    $report = DailyReport::query()->firstOrFail();

    actingAs($this->engineer);
    Livewire::test(DailyReportsTable::class)->call('deleteOne', $report->id);

    expect(DailyReport::query()->count())->toBe(0)
        ->and((string) $this->stageB->refresh()->progress)->toBe('40.00');
});

it('stores photos and a video on the report', function () {
    Storage::fake('private');
    $image = UploadedFile::fake()->image('site.jpg', 800, 600);
    $photo = siteUpload($this->engineer, 'site.jpg', (string) file_get_contents($image->getPathname()));
    $video = siteUpload($this->engineer, 'walk.mp4', "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 2048));

    actingAs($this->engineer)->post(route('daily-reports.store'), ($this->payload)(['uploads' => [$photo->id, $video->id]]))->assertSessionHasNoErrors();

    expect(DailyReport::query()->firstOrFail()->getMedia('attachments'))->toHaveCount(2);
});

it('engineer cannot report on another engineer\'s project and does not see its reports', function () {
    $foreign = Project::factory()->create();
    $stage = $foreign->stages()->create(['name_ar' => 'التشطيب', 'sort_order' => 1, 'weight' => '10', 'status' => 'not_started', 'progress' => '0']);
    $report = DailyReport::factory()->for($foreign)->create(['report_date' => '2026-11-15']);

    actingAs($this->engineer)->post(route('daily-reports.store'), ($this->payload)(['project_id' => $foreign->id, 'stages' => [$stage->id => '20']]))
        ->assertSessionHasErrors('project_id');
    actingAs($this->engineer)->get(route('daily-reports.show', $report))->assertNotFound();

    expect(Livewire::test(DailyReportsTable::class)->viewData('rows')->total())->toBe(0)
        ->and((string) $stage->refresh()->progress)->toBe('0.00');
});

it('ignores stages of another project', function () {
    $foreignStage = Project::factory()->create()->stages()->create(['name_ar' => 'س', 'sort_order' => 1, 'weight' => '10', 'status' => 'not_started', 'progress' => '0']);

    actingAs($this->engineer)->post(route('daily-reports.store'), ($this->payload)(['stages' => [$foreignStage->id => '50']]))
        ->assertSessionHasErrors('stages');
});

it('filters by project, engineer and has obstacles, and by date range', function () {
    $manager = scopedUser(['projects.view', 'daily_reports.view']);
    $second = Project::factory()->create();
    DailyReport::factory()->for($this->project)->create(['report_date' => '2026-11-10', 'engineer_id' => $this->engineer->id, 'obstacles' => 'مطر', 'has_obstacles' => true]);
    DailyReport::factory()->for($this->project)->create(['report_date' => '2026-11-11', 'engineer_id' => $this->engineer->id]);
    DailyReport::factory()->for($second)->create(['report_date' => '2026-10-20']);

    actingAs($manager);

    expect(Livewire::test(DailyReportsTable::class)->viewData('rows')->total())->toBe(3)
        ->and(Livewire::test(DailyReportsTable::class)->set('filters.project_id', (string) $this->project->id)->viewData('rows')->total())->toBe(2)
        ->and(Livewire::test(DailyReportsTable::class)->set('filters.engineer_id', (string) $this->engineer->id)->viewData('rows')->total())->toBe(2)
        ->and(Livewire::test(DailyReportsTable::class)->set('filters.has_obstacles', '1')->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(DailyReportsTable::class)->call('setDateRange', '2026-11-01', '2026-11-30')->viewData('rows')->total())->toBe(2);

    $this->get(route('daily-reports.index'))->assertOk();
    $this->get(route('projects.show', [$this->project, 'tab' => 'daily_reports']))->assertOk();
});
