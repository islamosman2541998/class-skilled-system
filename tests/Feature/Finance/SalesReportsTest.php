<?php

use App\Enums\LookupType;
use App\Livewire\Tables\Reports\LateWorkReport;
use App\Livewire\Tables\Reports\SalesPerformanceReport;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\ReportRow;
use App\Models\User;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->user = scopedUser(['reports.view', 'reports.export', 'leads.view', 'quotations.view', 'projects.view']);
    actingAs($this->user);
});

it('conversion rate counts only decided and sent quotations in the range', function () {
    $seller = User::factory()->create();
    $quote = fn (string $status, string $date = '2026-11-10', array $extra = []) => Quotation::factory()
        ->create(['assigned_to' => $seller->id, 'issue_date' => $date])
        ->forceFill(['status' => $status, 'total' => '1000.00'] + $extra)->save();

    $quote('accepted');
    $quote('accepted', '2026-11-30');
    $quote('rejected');
    $quote('sent');
    $quote('draft');
    $quote('draft');
    $quote('expired');
    $quote('accepted', '2026-10-20');
    $quote('accepted', '2026-11-12', ['superseded_at' => now()]);

    $table = Livewire::test(SalesPerformanceReport::class)
        ->call('setDateRange', '2026-11-01', '2026-11-30')
        ->set('filters.group', 'assignee');
    /** @var ReportRow $row */
    $row = $table->viewData('rows')->firstWhere('id', $seller->id);

    expect((int) $row->quotations_count)->toBe(7)
        ->and((int) $row->accepted_count)->toBe(2)
        ->and((int) $row->draft_count)->toBe(2)
        ->and(bcadd((string) $row->accepted_value, '0', 2))->toBe('2000.00')
        ->and(bcadd((string) $row->conversion_rate, '0', 2))->toBe('50.00');
});

it('counts leads of the range by source and by service', function () {
    $source = Lookup::query()->ofType(LookupType::LeadSource)->firstOrFail();
    $service = Lookup::query()->ofType(LookupType::Service)->firstOrFail();
    Lead::factory()->count(3)->create(['source_id' => $source->id, 'service_id' => $service->id, 'created_at' => '2026-11-05 09:00']);
    Lead::factory()->create(['source_id' => $source->id, 'service_id' => $service->id, 'created_at' => '2026-10-05 09:00']);
    Lead::factory()->create(['source_id' => null, 'service_id' => $service->id, 'created_at' => '2026-11-06 09:00']);

    $bySource = Livewire::test(SalesPerformanceReport::class)->call('setDateRange', '2026-11-01', '2026-11-30');
    expect((int) $bySource->viewData('rows')->firstWhere('id', $source->id)->leads_count)->toBe(3)
        ->and((int) $bySource->viewData('rows')->firstWhere('id', 0)->leads_count)->toBe(1)
        ->and($bySource->viewData('rows')->sum(fn (ReportRow $row) => (int) $row->leads_count))->toBe(4);

    $byService = Livewire::test(SalesPerformanceReport::class)->call('setDateRange', '2026-11-01', '2026-11-30')->set('filters.group', 'service');
    expect((int) $byService->viewData('rows')->firstWhere('id', $service->id)->leads_count)->toBe(4);
});

it('late work lists a stage past its planned end and a running project past its end date', function () {
    $late = Project::factory()->create(['planned_end_date' => '2026-11-01']);
    Project::factory()->create(['planned_end_date' => '2026-11-01', 'status' => 'completed']);
    $onTime = Project::factory()->create(['planned_end_date' => '2026-12-31']);
    $onTime->stages()->create(['name_ar' => 'الحفر', 'sort_order' => 1, 'weight' => '10', 'planned_end' => '2026-11-10', 'status' => 'in_progress', 'progress' => '0']);
    $onTime->stages()->create(['name_ar' => 'الأساسات', 'sort_order' => 2, 'weight' => '10', 'planned_end' => '2026-11-01', 'status' => 'done', 'progress' => '100']);
    $onTime->stages()->create(['name_ar' => 'الهيكل', 'sort_order' => 3, 'weight' => '10', 'planned_end' => '2026-12-01', 'status' => 'not_started', 'progress' => '0']);
    $cancelled = Project::factory()->create(['planned_end_date' => '2026-12-31', 'status' => 'cancelled']);
    $cancelled->stages()->create(['name_ar' => 'التشطيب', 'sort_order' => 1, 'weight' => '10', 'planned_end' => '2026-11-01', 'status' => 'in_progress', 'progress' => '0']);

    $rows = Livewire::test(LateWorkReport::class)->viewData('rows');

    expect($rows->total())->toBe(2)
        ->and($rows->map(fn (ReportRow $row) => [$row->type, (int) $row->project_id, (int) $row->days_late])->sortBy(2)->values()->all())
        ->toBe([['stage', $onTime->id, 6], ['project', $late->id, 15]]);

    $stagesOnly = Livewire::test(LateWorkReport::class)->set('filters.type', 'stage');
    expect($stagesOnly->viewData('rows')->total())->toBe(1);
});

it('late work covers only the projects an engineer may see', function () {
    $engineer = scopedUser(['reports.view', 'projects.view'], 'own');
    Project::factory()->create(['planned_end_date' => '2026-11-01', 'manager_id' => $engineer->id]);
    Project::factory()->create(['planned_end_date' => '2026-11-01']);

    actingAs($engineer);

    expect(Livewire::test(LateWorkReport::class)->viewData('rows')->total())->toBe(1);
});

it('opens and exports the sales and late work reports', function (string $key, string $component) {
    $this->get(route('reports.index'))->assertOk()->assertSee(__('reports.titles.'.$key));
    $this->get(route('reports.show', $key))->assertOk();

    Livewire::test($component)->call('export')->assertFileDownloaded();
})->with([
    ['sales', SalesPerformanceReport::class],
    ['late', LateWorkReport::class],
]);

it('sales report needs leads.view and quotations.view', function () {
    actingAs(scopedUser(['reports.view', 'projects.view']));

    $this->get(route('reports.show', 'sales'))->assertForbidden();
    $this->get(route('reports.show', 'late'))->assertOk();
});
