<?php

use App\Actions\Finance\SaveExpense;
use App\Actions\Resources\BillSubcontractPayment;
use App\Actions\Resources\SaveAttendance;
use App\Actions\Resources\SaveEquipmentAssignment;
use App\Actions\Resources\SavePurchase;
use App\Actions\Resources\SaveSubcontract;
use App\Enums\LookupType;
use App\Livewire\Tables\Reports\ProfitabilityReport;
use App\Models\Equipment;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Worker;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['reports.view', 'finance.profitability', 'projects.view']);
    $this->project = Project::factory()->create(['start_date' => '2026-11-01']);

    // Expenses 1,000 + materials 2,000 + subcontract bill 3,000 + labour 450 + equipment 700.
    app(SaveExpense::class)->handle(['project_id' => $this->project->id, 'category_id' => Lookup::query()->ofType(LookupType::ExpenseCategory)->value('id'), 'amount' => '1000', 'vat_mode' => 'none'], $this->user);
    app(SavePurchase::class)->handle([
        'supplier_id' => Supplier::factory()->create()->id, 'project_id' => $this->project->id, 'purchase_date' => '2026-11-05', 'supplier_invoice_no' => 'M-1', 'vat' => true,
        'items' => [['description_ar' => 'أسمنت', 'quantity' => '20', 'unit_price' => '100']],
    ], $this->user);
    $subcontract = app(SaveSubcontract::class)->handle([
        'supplier_id' => Supplier::factory()->subcontractor()->create()->id, 'project_id' => $this->project->id, 'scope' => 'جبس', 'value' => '10000', 'vat' => true, 'start_date' => '2026-11-01',
        'payments' => [['label_ar' => 'دفعة أولى', 'amount' => '3000']],
    ], $this->user);
    app(BillSubcontractPayment::class)->handle($subcontract->payments()->firstOrFail(), $this->user);
    $worker = Worker::factory()->create(['daily_wage' => '150']);
    app(SaveAttendance::class)->handle($this->project, Carbon::parse('2026-11-10'), [$worker->id => ['status' => 'present']], $this->user);
    app(SaveAttendance::class)->handle($this->project, Carbon::parse('2026-11-11'), [$worker->id => ['status' => 'present']], $this->user);
    app(SaveAttendance::class)->handle($this->project, Carbon::parse('2026-11-12'), [$worker->id => ['status' => 'present']], $this->user);
    app(SaveEquipmentAssignment::class)->handle([
        'equipment_id' => Equipment::factory()->create(['daily_cost' => '350'])->id, 'project_id' => $this->project->id, 'date_from' => '2026-11-03', 'date_to' => '2026-11-04',
    ], $this->user);
});

it('project cost total is expenses + purchases + labour + equipment', function () {
    expect($this->project->fresh()->cost_total)->toBe('7150.00');
});

it('profitability shows materials, subcontracts, labour, equipment and expenses columns that add up to the cost', function () {
    actingAs($this->user);
    $row = Livewire::test(ProfitabilityReport::class)->viewData('rows')->firstWhere('id', $this->project->id);
    $money = fn (string $key) => number_format((float) $row->getAttribute($key), 2, '.', '');

    expect($money('cost_materials'))->toBe('2000.00')
        ->and($money('cost_subcontracts'))->toBe('3000.00')
        ->and($money('cost_labour'))->toBe('450.00')
        ->and($money('cost_equipment'))->toBe('700.00')
        ->and($money('cost_expenses'))->toBe('1000.00')
        ->and($money('cost_total'))->toBe('7150.00');
});
