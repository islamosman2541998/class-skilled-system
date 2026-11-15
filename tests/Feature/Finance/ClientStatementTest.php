<?php

use App\Enums\LookupType;
use App\Enums\ReceiptStatus;
use App\Livewire\Tables\Reports\ClientStatementReport;
use App\Models\Invoice;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\Receipt;
use App\Models\ReportRow;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->user = scopedUser(['reports.view', 'reports.export', 'invoices.view', 'receipts.view', 'clients.view']);
    $this->project = Project::factory()->create();
    $this->client = $this->project->client;

    $invoice = fn (string $date, string $total, string $status = 'issued') => Invoice::factory()->for($this->project)->issued()
        ->create(['issue_date' => $date, 'total' => $total, 'status' => $status]);
    $receipt = function (string $date, string $amount, string $status = 'active') {
        $receipt = new Receipt(['receipt_date' => $date, 'amount' => $amount, 'payment_method_id' => Lookup::query()->ofType(LookupType::PaymentMethod)->value('id'), 'received_by' => $this->user->id]);
        $receipt->forceFill(['number' => 'RV-T-'.fake()->unique()->numberBetween(1, 999999), 'client_id' => $this->client->id, 'project_id' => $this->project->id, 'status' => ReceiptStatus::from($status)])->save();
    };

    $invoice('2026-10-05', '1000.00');
    $invoice('2026-11-03', '1050.00');
    Invoice::factory()->for($this->project)->create(['issue_date' => '2026-11-04', 'total' => '500.00']);
    $invoice('2026-11-05', '700.00', 'cancelled');
    $invoice('2026-11-30', '200.00');
    $invoice('2026-12-01', '999.00');
    $receipt('2026-10-10', '400.00');
    $receipt('2026-11-10', '600.00');
    $receipt('2026-11-11', '300.00', 'cancelled');

    // Another client's documents never show.
    Invoice::factory()->for(Project::factory())->issued()->create(['issue_date' => '2026-11-03', 'total' => '777.00']);

    actingAs($this->user);
});

it('statement running balance and opening balance are exact', function () {
    $table = Livewire::test(ClientStatementReport::class)
        ->set('filters.client_id', (string) $this->client->id)
        ->call('setDateRange', '2026-11-01', '2026-11-30');

    $rows = $table->viewData('rows');

    expect($rows->total())->toBe(4)
        ->and($rows->map(fn (ReportRow $row) => [$row->entry, bcadd((string) $row->balance, '0', 2)])->all())->toBe([
            ['opening', '600.00'],
            ['invoice', '1650.00'],
            ['receipt', '1050.00'],
            ['invoice', '1250.00'],
        ])
        ->and($table->viewData('totals')['debit'])->toBe('1,250.00')
        ->and($table->viewData('totals')['credit'])->toBe('600.00');
});

it('statement without a start date opens at zero and lists everything', function () {
    $rows = Livewire::test(ClientStatementReport::class)->set('filters.client_id', (string) $this->client->id)->viewData('rows');

    expect($rows->total())->toBe(6)
        ->and(bcadd((string) $rows->last()->balance, '0', 2))->toBe('2249.00');
});

it('statement is empty until a client is chosen', function () {
    expect(Livewire::test(ClientStatementReport::class)->viewData('rows')->total())->toBe(0);
});

it('statement PDF renders in three languages', function (string $lang) {
    $response = $this->get(route('clients.statement.pdf', [$this->client, 'lang' => $lang, 'from' => '2026-11-01', 'to' => '2026-11-30']));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
})->with(['ar', 'en', 'both']);

it('statement PDF follows the report permissions and the client visibility', function () {
    actingAs(scopedUser(['reports.view', 'invoices.view', 'clients.view']));
    $this->get(route('clients.statement.pdf', $this->client))->assertForbidden();

    actingAs(scopedUser(['reports.view', 'invoices.view', 'receipts.view', 'clients.view'], 'own'));
    $this->get(route('clients.statement.pdf', $this->client))->assertNotFound();
});

it('client page links to the statement and its PDF', function () {
    $this->get(route('clients.show', $this->client))
        ->assertOk()
        ->assertSee(route('reports.show', ['statement', 'f' => ['client_id' => $this->client->id]]), false)
        ->assertSee(route('clients.statement.pdf', [$this->client, 'lang' => 'ar']), false);
});
