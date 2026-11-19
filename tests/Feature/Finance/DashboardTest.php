<?php

use App\Enums\LookupType;
use App\Enums\ReceiptStatus;
use App\Livewire\Tables\InvoicesTable;
use App\Livewire\Tables\LeadsTable;
use App\Livewire\Tables\Reports\InstallmentsReport;
use App\Models\DashboardPreference;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->figure = fn (TestResponse $response, string $key) => collect($response->viewData('figures'))->flatten(1)->firstWhere('key', $key);
    $this->keys = fn (TestResponse $response) => collect($response->viewData('figures'))->flatten(1)->pluck('key')->all();
    $this->management = fn () => scopedUser([
        'projects.view', 'leads.view', 'quotations.view', 'site_visits.view', 'invoices.view', 'receipts.view',
        'expenses.view', 'finance.costs', 'finance.profitability', 'reports.view',
    ]);
});

function dashboardReceipt(Project $project, string $date, string $amount, string $status = 'active'): void
{
    $receipt = new Receipt(['receipt_date' => $date, 'amount' => $amount, 'payment_method_id' => Lookup::query()->ofType(LookupType::PaymentMethod)->value('id'), 'received_by' => User::factory()->create()->id]);
    $receipt->forceFill(['number' => 'RV-T-'.fake()->unique()->numberBetween(1, 999999), 'client_id' => $project->client_id, 'project_id' => $project->id, 'status' => ReceiptStatus::from($status)])->save();
}

function dashboardExpense(Project $project, string $date, string $amount, string $vat): void
{
    $expense = new Expense(['project_id' => $project->id, 'category_id' => Lookup::query()->ofType(LookupType::ExpenseCategory)->value('id'), 'expense_date' => $date]);
    $expense->forceFill(['number' => 'EXP-T-'.fake()->unique()->numberBetween(1, 999999), 'amount' => $amount, 'vat_rate' => '5.00', 'vat_amount' => $vat, 'total' => bcadd($amount, $vat, 2)])->save();
}

it('management sees money figures with exact values for the period', function () {
    $project = Project::factory()->create(['contract_value' => '100000.00', 'start_date' => '2026-11-02']);
    Project::factory()->create(['contract_value' => '50000.00', 'start_date' => '2026-10-01']);
    Invoice::factory()->for($project)->issued()->create(['issue_date' => '2026-11-05', 'due_date' => '2026-11-10', 'subtotal' => '1000.00', 'vat_amount' => '50.00', 'total' => '1050.00']);
    Invoice::factory()->for($project)->issued()->create(['issue_date' => '2026-10-05', 'due_date' => '2026-10-20', 'subtotal' => '2000.00', 'vat_amount' => '100.00', 'total' => '2100.00', 'paid_amount' => '2100.00', 'status' => 'paid']);
    Invoice::factory()->for($project)->create(['issue_date' => '2026-11-06', 'subtotal' => '9000.00', 'total' => '9450.00']);
    dashboardReceipt($project, '2026-11-12', '2100.00');
    dashboardReceipt($project, '2026-11-13', '500.00', 'cancelled');
    dashboardExpense($project, '2026-11-08', '400.00', '20.00');

    $response = actingAs(($this->management)())->get(route('home'))->assertOk();

    expect(($this->figure)($response, 'contracts_total')['value'])->toBe(money('100000'))
        ->and(($this->figure)($response, 'collected')['value'])->toBe(money('2100'))
        ->and(($this->figure)($response, 'outstanding')['value'])->toBe(money('1050'))
        ->and(($this->figure)($response, 'overdue_collection')['value'])->toBe(money('1050'))
        ->and(($this->figure)($response, 'overdue_invoices')['value'])->toBe('1')
        ->and(($this->figure)($response, 'period_expenses')['value'])->toBe(money('420'))
        ->and(($this->figure)($response, 'period_profit')['value'])->toBe(money('600'))
        ->and(($this->figure)($response, 'running_projects')['value'])->toBe('2');

    $october = $this->get(route('home', ['from' => '2026-10-01', 'to' => '2026-10-31']));
    expect(($this->figure)($october, 'contracts_total')['value'])->toBe(money('50000'))
        ->and(($this->figure)($october, 'collected')['value'])->toBe(money('0'))
        ->and(($this->figure)($october, 'period_profit')['value'])->toBe(money('2000'));
});

it('engineer sees only own projects counts and no money', function () {
    $engineer = scopedUser(['projects.view', 'site_visits.view'], 'own');
    $mine = Project::factory()->create(['manager_id' => $engineer->id, 'planned_end_date' => '2026-11-01']);
    $mine->stages()->create(['name_ar' => 'الحفر', 'sort_order' => 1, 'weight' => '10', 'planned_end' => '2026-11-10', 'status' => 'in_progress', 'progress' => '0']);
    Project::factory()->count(2)->create(['planned_end_date' => '2026-11-01']);

    $response = actingAs($engineer)->get(route('home'))->assertOk();

    expect(($this->keys)($response))->toEqualCanonicalizing(['running_projects', 'late_projects', 'late_stages', 'visits_today'])
        ->and(($this->figure)($response, 'running_projects')['value'])->toBe('1')
        ->and(($this->figure)($response, 'late_projects')['value'])->toBe('1')
        ->and(($this->figure)($response, 'late_stages')['value'])->toBe('1')
        ->and($response->viewData('chart'))->toBeNull();
});

it('every figure link opens its list and the list total matches the figure', function () {
    $user = scopedUser(['invoices.view', 'reports.view', 'projects.view']);
    $project = Project::factory()->create();
    Invoice::factory()->for($project)->issued()->count(2)->create(['due_date' => '2026-11-01']);
    Invoice::factory()->for($project)->issued()->create(['due_date' => '2026-12-01']);
    foreach (['due', 'due', 'not_due'] as $i => $status) {
        $project->installments()->create(['label_ar' => 'دفعة '.$i, 'amount' => '1000', 'due_type' => 'date', 'due_date' => '2026-11-20', 'status' => $status, 'sort_order' => $i]);
    }

    $response = actingAs($user)->get(route('home'));

    foreach ([['overdue_invoices', InvoicesTable::class], ['due_installments', InstallmentsReport::class]] as [$key, $component]) {
        $figure = ($this->figure)($response, $key);
        $this->get($figure['url'])->assertOk();
        parse_str((string) parse_url($figure['url'], PHP_URL_QUERY), $query);

        expect($figure['value'])->toBe('2')
            ->and(Livewire::withQueryParams($query)->test($component)->viewData('rows')->total())->toBe(2);
    }
});

it('my leads link opens the leads of the period assigned to the user', function () {
    $seller = scopedUser(['leads.view'], 'own');
    Lead::factory()->count(2)->create(['assigned_to' => $seller->id, 'created_at' => '2026-11-03 09:00']);
    Lead::factory()->create(['assigned_to' => $seller->id, 'created_at' => '2026-10-03 09:00']);
    Lead::factory()->create(['created_at' => '2026-11-04 09:00']);

    $figure = ($this->figure)(actingAs($seller)->get(route('home')), 'new_leads');
    parse_str((string) parse_url($figure['url'], PHP_URL_QUERY), $query);

    $this->get($figure['url'])->assertOk();
    expect($figure['value'])->toBe('2')
        ->and(Livewire::withQueryParams($query)->test(LeadsTable::class)->viewData('rows')->total())->toBe(2);
});

it('chart has six months of revenue and cost', function () {
    $project = Project::factory()->create();
    Invoice::factory()->for($project)->issued()->create(['issue_date' => '2026-11-05', 'subtotal' => '1000.00']);
    Invoice::factory()->for($project)->issued()->create(['issue_date' => '2026-05-31', 'subtotal' => '7000.00']);
    dashboardExpense($project, '2026-06-08', '400.00', '20.00');

    $response = actingAs(($this->management)())->get(route('home'))->assertOk()->assertSee('chart-plot', false);
    $chart = $response->viewData('chart');

    expect($chart)->toHaveCount(6)
        ->and(array_column($chart, 'month'))->toBe(['2026-06', '2026-07', '2026-08', '2026-09', '2026-10', '2026-11'])
        ->and($chart[0]['cost'])->toBe('400.00')
        ->and($chart[5]['revenue'])->toBe('1000.00');
});

it('shows project and sales charts with data allowed by permissions', function () {
    $user = ($this->management)();
    Project::factory()->count(2)->create(['status' => 'in_progress']);
    Project::factory()->create(['status' => 'completed']);
    Lead::factory()->count(3)->create([
        'assigned_to' => $user->id,
        'created_at' => '2026-11-10 09:00:00',
    ]);

    $response = actingAs($user)->get(route('home'))->assertOk()
        ->assertSee('donut-chart', false)
        ->assertSee('horizontal-chart', false);
    $charts = $response->viewData('charts');

    expect($charts)->toHaveKeys(['revenue_cost', 'project_status', 'sales_pipeline'])
        ->and(collect($charts['project_status']['data'])->sum('value'))->toBe(3)
        ->and(collect($charts['sales_pipeline']['data'])->sum('value'))->toBe(3);
});

it('stores each user chart visibility preferences', function () {
    $user = ($this->management)();

    actingAs($user)->put(route('dashboard.widgets.update'), [
        'visible_widgets' => ['project_status'],
        'from' => '2026-11-01',
        'to' => '2026-11-30',
    ])->assertRedirect(route('home', ['from' => '2026-11-01', 'to' => '2026-11-30']));

    expect(DashboardPreference::query()->where('user_id', $user->id)->first()->hidden_widgets)
        ->toEqualCanonicalizing(['revenue_cost', 'sales_pipeline']);

    $response = $this->get(route('home'))->assertOk();
    expect(array_keys($response->viewData('charts')))->toBe(['project_status'])
        ->and($response->viewData('chart'))->toBeNull();
});
