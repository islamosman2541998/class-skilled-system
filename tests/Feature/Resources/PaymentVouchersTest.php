<?php

use App\Actions\Resources\AllocatePaymentVoucher;
use App\Enums\LookupType;
use App\Enums\PaymentVoucherStatus;
use App\Enums\PurchaseStatus;
use App\Models\Lookup;
use App\Models\PaymentVoucher;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Supplier;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['projects.view', 'suppliers.view', 'purchases.view', 'purchases.update', 'purchases.delete', 'payment_vouchers.view', 'payment_vouchers.create', 'payment_vouchers.update', 'payment_vouchers.cancel']);
    $this->supplier = Supplier::factory()->create();
    $this->project = Project::factory()->create();
    $this->cash = Lookup::query()->ofType(LookupType::PaymentMethod)->where('code', 'cash')->value('id');
    $this->first = Purchase::factory()->create(['supplier_id' => $this->supplier->id, 'project_id' => $this->project->id, 'total' => '1050.00']);
    $this->second = Purchase::factory()->create(['supplier_id' => $this->supplier->id, 'project_id' => $this->project->id, 'total' => '2100.00']);
});

function voucherPayload(array $overrides = []): array
{
    return array_merge([
        'supplier_id' => test()->supplier->id,
        'voucher_date' => '2026-11-15',
        'amount' => '2000',
        'payment_method_id' => test()->cash,
        'reference' => 'TRX-881',
    ], $overrides);
}

it('pays two purchase invoices partly and fully and their statuses follow', function () {
    actingAs($this->user)->post(route('payment-vouchers.store'), voucherPayload(['allocations' => [
        $this->first->id => '1050', $this->second->id => '950',
    ]]))->assertRedirect();

    $voucher = PaymentVoucher::query()->sole();

    expect($voucher->number)->toBe('PV-2026-0001')
        ->and($voucher->status)->toBe(PaymentVoucherStatus::Active)
        ->and($voucher->allocations)->toHaveCount(2)
        ->and($this->first->fresh()->status)->toBe(PurchaseStatus::Paid)
        ->and($this->first->fresh()->paid_amount)->toBe('1050.00')
        ->and($this->second->fresh()->status)->toBe(PurchaseStatus::Partial)
        ->and($this->second->fresh()->paid_amount)->toBe('950.00');

    actingAs($this->user)->get(route('payment-vouchers.show', $voucher))->assertOk()->assertSee($this->first->number);
});

it('renders the payment vouchers list with allocated totals', function () {
    actingAs($this->user)->post(route('payment-vouchers.store'), voucherPayload(['allocations' => [
        $this->first->id => '1050', $this->second->id => '950',
    ]]))->assertRedirect();

    actingAs($this->user)->get(route('payment-vouchers.index'))
        ->assertOk()
        ->assertSee('PV-2026-0001');
});

it('refuses more than an invoice remaining or the voucher amount', function () {
    actingAs($this->user)->post(route('payment-vouchers.store'), voucherPayload(['allocations' => [$this->first->id => '1100']]))
        ->assertSessionHasErrors('allocations');
    actingAs($this->user)->post(route('payment-vouchers.store'), voucherPayload(['amount' => '500', 'allocations' => [$this->first->id => '600']]))
        ->assertSessionHasErrors('allocations');

    expect(PaymentVoucher::query()->count())->toBe(0)->and($this->first->fresh()->paid_amount)->toBe('0.00');
});

it('two vouchers on the same invoice never pay more than its total', function () {
    actingAs($this->user)->post(route('payment-vouchers.store'), voucherPayload(['amount' => '1000', 'allocations' => [$this->first->id => '1000']]));
    actingAs($this->user)->post(route('payment-vouchers.store'), voucherPayload(['amount' => '1000']));
    $second = PaymentVoucher::query()->latest('id')->firstOrFail();

    expect(fn () => app(AllocatePaymentVoucher::class)->handle($second, [$this->first->id => '100'], $this->user))->toThrow(ValidationException::class);

    app(AllocatePaymentVoucher::class)->handle($second, [$this->first->id => '50'], $this->user);
    expect($this->first->fresh()->paid_amount)->toBe('1050.00')->and($this->first->fresh()->status)->toBe(PurchaseStatus::Paid);
});

it('cancelling a voucher returns the invoices to unpaid or partial and keeps the voucher visible as cancelled', function () {
    actingAs($this->user)->post(route('payment-vouchers.store'), voucherPayload(['amount' => '1500', 'allocations' => [$this->first->id => '1050', $this->second->id => '450']]));
    actingAs($this->user)->post(route('payment-vouchers.store'), voucherPayload(['amount' => '500', 'reference' => 'B', 'allocations' => [$this->second->id => '500']]));
    $voucher = PaymentVoucher::query()->orderBy('id')->firstOrFail();

    actingAs($this->user)->patch(route('payment-vouchers.cancel', $voucher), ['reason' => 'شيك مرتجع'])->assertRedirect();
    actingAs($this->user)->patch(route('payment-vouchers.cancel', $voucher), ['reason' => 'مرة ثانية'])->assertRedirect();

    $voucher->refresh();
    expect($voucher->status)->toBe(PaymentVoucherStatus::Cancelled)
        ->and($voucher->cancel_reason)->toBe('شيك مرتجع')
        ->and($this->first->fresh()->status)->toBe(PurchaseStatus::Unpaid)
        ->and($this->second->fresh()->status)->toBe(PurchaseStatus::Partial)
        ->and($this->second->fresh()->paid_amount)->toBe('500.00');

    actingAs($this->user)->get(route('payment-vouchers.show', $voucher))->assertOk()->assertSee('شيك مرتجع');
});

it('a paid purchase cannot be deleted and its supplier cannot change', function () {
    actingAs($this->user)->post(route('payment-vouchers.store'), voucherPayload(['amount' => '100', 'allocations' => [$this->first->id => '100']]));

    actingAs($this->user)->delete(route('purchases.destroy', $this->first))->assertSessionHas('error');
    expect(Purchase::query()->whereKey($this->first->id)->exists())->toBeTrue();

    $this->first->items()->create(['description_ar' => 'مواد', 'quantity' => '1', 'unit_price' => '1000', 'line_total' => '1000', 'sort_order' => 1]);
    actingAs($this->user)->put(route('purchases.update', $this->first), [
        'supplier_id' => Supplier::factory()->create()->id, 'project_id' => $this->project->id, 'purchase_date' => '2026-11-14',
        'supplier_invoice_no' => $this->first->supplier_invoice_no, 'vat' => '1',
        'items' => [['description_ar' => 'مواد', 'quantity' => '1', 'unit_price' => '1000']],
    ])->assertSessionHasErrors('supplier_id');
});

it('supplier page shows purchases, paid, due and projects', function () {
    actingAs($this->user)->post(route('payment-vouchers.store'), voucherPayload(['amount' => '1050', 'allocations' => [$this->first->id => '1050']]));

    $response = actingAs($this->user)->get(route('suppliers.show', $this->supplier))->assertOk();
    $figures = $response->viewData('figures');

    expect($figures['purchases_total'])->toBe(money('3150.00'))
        ->and($figures['paid'])->toBe(money('1050.00'))
        ->and($figures['due'])->toBe(money('2100.00'))
        ->and($figures['projects'])->toBe('1');

    actingAs($this->user)->get(route('suppliers.show', [$this->supplier, 'tab' => 'payment_vouchers']))->assertOk()->assertSee('PV-2026-0001');
    actingAs($this->user)->get(route('suppliers.show', [$this->supplier, 'tab' => 'projects']))->assertOk()->assertSee($this->project->number);
});

it('refuses allocating to another supplier\'s invoice', function () {
    $foreign = Purchase::factory()->create(['total' => '500.00']);

    actingAs($this->user)->post(route('payment-vouchers.store'), voucherPayload(['allocations' => [$foreign->id => '100']]))
        ->assertSessionHasErrors('allocations');

    expect($foreign->fresh()->paid_amount)->toBe('0.00');
});
