<?php

use App\Livewire\Tables\Reports\MissingReportsReport;
use App\Models\DailyReport;
use App\Models\Project;
use App\Services\Site\ReportCalendar;
use App\Settings\SystemSettings;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    // Monday; Friday 13 November is the weekly day off by default.
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->engineer = scopedUser(['projects.view', 'daily_reports.view', 'daily_reports.create', 'reports.view'], 'assigned');
    $this->project = Project::factory()->create(['manager_id' => $this->engineer->id, 'start_date' => '2026-11-09', 'status' => 'in_progress']);
    DailyReport::factory()->for($this->project)->create(['report_date' => '2026-11-10']);
    DailyReport::factory()->for($this->project)->create(['report_date' => '2026-11-12']);
});

it('lists working days without a report since the project start and skips Fridays and today', function () {
    expect(ReportCalendar::missingDays($this->project, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-16')))
        ->toBe(['2026-11-09', '2026-11-11', '2026-11-14', '2026-11-15']);
});

it('a project not in progress has no missing days', function () {
    $this->project->update(['status' => 'on_hold']);

    expect(ReportCalendar::missingDays($this->project->refresh(), Carbon::parse('2026-11-01'), Carbon::parse('2026-11-16')))->toBe([]);
});

it('changing the weekly days off changes the days', function () {
    $settings = app(SystemSettings::class);
    $settings->weekly_days_off = [6, 7];
    $settings->save();

    expect(ReportCalendar::missingDays($this->project, Carbon::parse('2026-11-01'), Carbon::parse('2026-11-16')))
        ->toBe(['2026-11-09', '2026-11-11', '2026-11-13']);

    actingAs(scopedUser(['settings.manage']))
        ->put(route('settings.update', 'system'), ['session_idle_minutes' => 60, 'public_link_hours' => 72, 'backup_keep' => 7, 'weekly_days_off' => ['5', '6']])
        ->assertSessionHasNoErrors();
    expect(app(SystemSettings::class)->weekly_days_off)->toBe([5, 6]);
});

it('engineer figure counts only own projects and its link lists the same rows', function () {
    Project::factory()->create(['start_date' => '2026-11-09', 'status' => 'in_progress']);

    $response = actingAs($this->engineer)->get(route('home'))->assertOk();
    $figure = collect($response->viewData('figures'))->flatten(1)->firstWhere('key', 'reports_not_sent');
    parse_str((string) parse_url($figure['url'], PHP_URL_QUERY), $query);

    expect($figure['value'])->toBe('4')
        ->and($query)->toBe(['from' => '2026-11-09', 'to' => '2026-11-15']);

    $this->get($figure['url'])->assertOk();
    $rows = Livewire::withQueryParams($query)->test(MissingReportsReport::class)->viewData('rows');
    expect($rows->total())->toBe(4)
        ->and($rows->pluck('day')->map(fn ($day) => substr((string) $day, 0, 10))->sort()->values()->all())
        ->toBe(['2026-11-09', '2026-11-11', '2026-11-14', '2026-11-15']);
});

it('project tab shows the days without a report of the last 30 days', function () {
    actingAs($this->engineer)->get(route('projects.show', [$this->project, 'tab' => 'daily_reports']))
        ->assertOk()
        ->assertSee('14/11/2026')
        ->assertSee(e(route('daily-reports.create', ['project' => $this->project->id, 'date' => '2026-11-14'])), false)
        ->assertDontSee('13/11/2026');
});
