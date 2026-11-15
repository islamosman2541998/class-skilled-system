<?php

use App\Enums\LookupType;
use App\Enums\ReceiptStatus;
use App\Livewire\Tables\Reports\CollectionsReport;
use App\Livewire\Tables\Reports\ExpensesReport;
use App\Livewire\Tables\Reports\ProfitabilityReport;
use App\Livewire\Tables\Reports\ReceivablesAgingReport;
use App\Livewire\Tables\Reports\VatSummaryReport;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Receipt;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->user = scopedUser(['reports.view', 'reports.export', 'invoices.view', 'receipts.view', 'expenses.view', 'finance.costs', 'finance.profitability']);
    $this->project = Project::factory()->create();
    $this->method = fn (string $code) => Lookup::query()->ofType(LookupType::PaymentMethod)->where('code', $code)->value('id');
    $this->receipt = function (string $date, string $amount, string $method = 'cash', string $status = 'active') {
        $receipt = new Receipt(['receipt_date' => $date, 'amount' => $amount, 'payment_method_id' => ($this->method)($method), 'received_by' => $this->user->id]);
        $receipt->forceFill(['number' => 'RV-T-'.fake()->unique()->numberBetween(1, 999999), 'client_id' => $this->project->client_id, 'project_id' => $this->project->id, 'status' => ReceiptStatus::from($status)])->save();

        return $receipt;
    };
    $this->expense = function (string $date, string $amount, string $vat, ?int $projectId = null, ?int $categoryId = null) {
        $expense = new Expense(['project_id' => $projectId, 'category_id' => $categoryId ?? Lookup::query()->ofType(LookupType::ExpenseCategory)->value('id'), 'expense_date' => $date]);
        $expense->forceFill(['number' => 'EXP-T-'.fake()->unique()->numberBetween(1, 999999), 'amount' => $amount, 'vat_rate' => '5.00', 'vat_amount' => $vat, 'total' => bcadd($amount, $vat, 2)])->save();

        return $expense;
    };
    actingAs($this->user);
});

it('collections count active receipts of the period, last day included', function () {
    ($this->receipt)('2026-11-30', '1000');
    ($this->receipt)('2026-11-05', '500', 'transfer');
    ($this->receipt)('2026-11-10', '700', 'cash', 'cancelled');
    ($this->receipt)('2026-12-01', '300');

    $table = Livewire::test(CollectionsReport::class)->call('setDateRange', '2026-11-01', '2026-11-30');

    expect($table->viewData('rows')->total())->toBe(2)
        ->and($table->viewData('totals')['amount'])->toBe('1,500.00');

    $daily = Livewire::test(CollectionsReport::class)->call('setDateRange', '2026-11-01', '2026-11-30')->set('filters.group', 'day');
    expect($daily->viewData('rows')->total())->toBe(2);

    $cash = Livewire::test(CollectionsReport::class)->call('setDateRange', '2026-11-01', '2026-11-30')->set('filters.payment_method_id', (string) ($this->method)('cash'));
    expect($cash->viewData('totals')['amount'])->toBe('1,000.00');
});

it('vat summary takes issued invoices and expenses of the period only', function () {
    Invoice::factory()->for($this->project)->issued()->create(['issue_date' => '2026-11-30', 'subtotal' => '1000.00', 'vat_amount' => '50.00', 'total' => '1050.00']);
    Invoice::factory()->for($this->project)->create(['issue_date' => '2026-11-10', 'vat_amount' => '99.00']);
    Invoice::factory()->for($this->project)->issued()->create(['issue_date' => '2026-11-12', 'vat_amount' => '77.00', 'status' => 'cancelled']);
    Invoice::factory()->for($this->project)->issued()->create(['issue_date' => '2026-10-31', 'subtotal' => '200.00', 'vat_amount' => '10.00']);
    ($this->expense)('2026-11-15', '400.00', '20.00', $this->project->id);

    $table = Livewire::test(VatSummaryReport::class)->call('setDateRange', '2026-11-01', '2026-11-30');
    $totals = $table->viewData('totals');

    expect($table->viewData('rows')->total())->toBe(1)
        ->and($totals['output_vat'])->toBe('50.00')
        ->and($totals['sales_net'])->toBe('1,000.00')
        ->and($totals['input_vat'])->toBe('20.00')
        ->and($totals['net_vat'])->toBe('30.00');
});

it('aging puts an invoice 45 days overdue in 31-60 and leaves out paid and drafts', function () {
    Invoice::factory()->for($this->project)->issued()->create(['total' => '1000.00', 'due_date' => '2026-10-02']);
    Invoice::factory()->for($this->project)->issued()->create(['total' => '500.00', 'paid_amount' => '200.00', 'status' => 'partially_paid', 'due_date' => '2026-11-20']);
    Invoice::factory()->for($this->project)->issued()->create(['total' => '800.00', 'paid_amount' => '800.00', 'status' => 'paid', 'due_date' => '2026-09-01']);
    Invoice::factory()->for($this->project)->create(['total' => '999.00', 'due_date' => '2026-09-01']);

    $totals = Livewire::test(ReceivablesAgingReport::class)->viewData('totals');

    expect($totals['outstanding'])->toBe('1,300.00')
        ->and($totals['days_31_60'])->toBe('1,000.00')
        ->and($totals['current_amount'])->toBe('300.00')
        ->and($totals['days_1_30'])->toBe('0.00')
        ->and($totals['days_over_90'])->toBe('0.00');
});

it('profitability shows profit and margin per project', function () {
    $this->project->forceFill(['invoiced_total' => '21000.00', 'invoiced_net' => '20000.00', 'cost_total' => '15000.00'])->save();

    $table = Livewire::test(ProfitabilityReport::class);
    $row = $table->viewData('rows')->first();

    expect(bcadd((string) $row->profit, '0', 2))->toBe('5000.00')
        ->and((float) $row->margin)->toBe(25.0)
        ->and($table->viewData('totals')['profit'])->toBe('5,000.00');
});

it('expenses report groups by category and project with general expenses apart', function () {
    $category = Lookup::query()->ofType(LookupType::ExpenseCategory)->value('id');
    ($this->expense)('2026-11-02', '100.00', '5.00', $this->project->id, $category);
    ($this->expense)('2026-11-03', '300.00', '15.00', $this->project->id, $category);
    ($this->expense)('2026-11-04', '50.00', '0.00', null, $category);

    $table = Livewire::test(ExpensesReport::class)->call('setDateRange', '2026-11-01', '2026-11-30');

    expect($table->viewData('rows')->total())->toBe(2)
        ->and($table->viewData('totals')['amount'])->toBe('450.00')
        ->and($table->viewData('totals')['total'])->toBe('470.00');
});

it('profitability report needs finance.profitability', function () {
    actingAs(scopedUser(['reports.view']));

    $this->get(route('reports.show', 'profitability'))->assertForbidden();
    $this->get(route('reports.index'))->assertOk()->assertDontSee(__('reports.titles.profitability'));
    Livewire::test(ProfitabilityReport::class)->assertForbidden();
});

it('opens every finance report page and exports each', function (string $key, string $component) {
    $this->get(route('reports.index'))->assertOk()->assertSee(__('reports.titles.'.$key));
    $this->get(route('reports.show', $key))->assertOk();

    Livewire::test($component)->call('export')->assertFileDownloaded();
})->with([
    ['profitability', ProfitabilityReport::class],
    ['aging', ReceivablesAgingReport::class],
    ['collections', CollectionsReport::class],
    ['expenses', ExpensesReport::class],
    ['vat', VatSummaryReport::class],
]);

it('engineer reports only cover own projects', function () {
    $engineer = scopedUser(['reports.view', 'receipts.view'], 'assigned');
    $own = Project::factory()->create(['manager_id' => $engineer->id]);
    ($this->receipt)('2026-11-10', '1000');
    $mine = ($this->receipt)('2026-11-10', '250');
    $mine->forceFill(['project_id' => $own->id, 'client_id' => $own->client_id])->save();

    actingAs($engineer);

    expect(Livewire::test(CollectionsReport::class)->viewData('totals')['amount'])->toBe('250.00');
});
