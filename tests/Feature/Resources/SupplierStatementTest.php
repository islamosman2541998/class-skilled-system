<?php

use App\Enums\LookupType;
use App\Enums\PaymentVoucherStatus;
use App\Livewire\Tables\Reports\SupplierStatementReport;
use App\Models\Lookup;
use App\Models\PaymentVoucher;
use App\Models\Purchase;
use App\Models\ReportRow;
use App\Models\Supplier;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['reports.view', 'purchases.view', 'payment_vouchers.view', 'suppliers.view']);
    $this->supplier = Supplier::factory()->create();

    $purchase = fn (string $date, string $total) => Purchase::factory()->create(['supplier_id' => $this->supplier->id, 'purchase_date' => $date, 'total' => $total]);
    $voucher = function (string $date, string $amount, string $status = 'active') {
        $voucher = new PaymentVoucher(['voucher_date' => $date, 'amount' => $amount, 'payment_method_id' => Lookup::query()->ofType(LookupType::PaymentMethod)->value('id')]);
        $voucher->forceFill(['number' => 'PV-T-'.fake()->unique()->numberBetween(1, 999999), 'supplier_id' => $this->supplier->id, 'status' => PaymentVoucherStatus::from($status)])->save();
    };

    $purchase('2026-10-05', '1000.00');
    $purchase('2026-11-03', '1050.00');
    $purchase('2026-11-20', '300.00');
    $voucher('2026-10-10', '400.00');
    $voucher('2026-11-10', '600.00');
    $voucher('2026-11-11', '250.00', 'cancelled');

    // Another supplier's documents never show.
    Purchase::factory()->create(['purchase_date' => '2026-11-03', 'total' => '777.00']);

    actingAs($this->user);
});

it('statement lists opening balance, purchases and vouchers in date order with the running balance', function () {
    $table = Livewire::test(SupplierStatementReport::class)
        ->set('filters.supplier_id', (string) $this->supplier->id)
        ->call('setDateRange', '2026-11-01', '2026-11-30');

    $rows = $table->viewData('rows');

    expect($rows->total())->toBe(4)
        ->and($rows->map(fn (ReportRow $row) => [$row->entry, bcadd((string) $row->balance, '0', 2)])->all())->toBe([
            ['opening', '600.00'],
            ['purchase', '1650.00'],
            ['voucher', '1050.00'],
            ['purchase', '1350.00'],
        ])
        ->and($table->viewData('totals')['credit'])->toBe('1,350.00')
        ->and($table->viewData('totals')['debit'])->toBe('600.00');
});

it('empty until a supplier is chosen', function () {
    expect(Livewire::test(SupplierStatementReport::class)->viewData('rows')->total())->toBe(0);
});

it('renders the supplier statement PDF in ar, en and both', function (string $lang) {
    $response = $this->get(route('suppliers.statement.pdf', [$this->supplier, 'lang' => $lang, 'from' => '2026-11-01', 'to' => '2026-11-30']));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
})->with(['ar', 'en', 'both']);

it('needs purchases.view and payment_vouchers.view', function () {
    actingAs(scopedUser(['reports.view', 'purchases.view', 'suppliers.view']));

    $this->get(route('suppliers.statement.pdf', $this->supplier))->assertForbidden();
    $this->get(route('reports.show', 'supplier-statement'))->assertForbidden();
});
