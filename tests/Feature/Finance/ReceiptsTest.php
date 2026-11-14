<?php

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\LookupType;
use App\Enums\ReceiptStatus;
use App\Livewire\Tables\ReceiptsTable;
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
    $this->user = scopedUser(['receipts.view', 'receipts.create', 'receipts.update', 'receipts.cancel', 'receipts.print', 'invoices.view', 'projects.view', 'clients.view']);
    $this->project = Project::factory()->create(['contract_value' => '100000.00']);
    $this->cash = Lookup::query()->ofType(LookupType::PaymentMethod)->where('code', 'cash')->value('id');
    $this->invoice = fn (string $total = '1050.00', array $extra = []) => Invoice::factory()->for($this->project)->issued()->create($extra + ['total' => $total]);
    $this->payload = fn (array $extra = []) => $extra + [
        'client_id' => $this->project->client_id,
        'project_id' => $this->project->id,
        'receipt_date' => '2026-11-16',
        'amount' => '500',
        'payment_method_id' => $this->cash,
        'received_by' => $this->user->id,
    ];
});

it('records a receipt RV-2026-0001 allocated to one invoice and marks it partially paid', function () {
    $invoice = ($this->invoice)();

    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['allocations' => [$invoice->id => '500']]))->assertRedirect();

    $receipt = Receipt::query()->firstOrFail();
    $invoice->refresh();

    expect($receipt->number)->toBe('RV-2026-0001')
        ->and($receipt->status)->toBe(ReceiptStatus::Active)
        ->and($receipt->unallocated())->toBe('0.00')
        ->and($invoice->paid_amount)->toBe('500.00')
        ->and($invoice->status)->toBe(InvoiceStatus::PartiallyPaid)
        ->and($this->project->fresh()->collected_total)->toBe('500.00');
});

it('allocating the rest marks the invoice paid and its installment collected', function () {
    $installment = $this->project->installments()->create(['label_ar' => 'دفعة', 'amount' => '1050.00', 'due_type' => 'date', 'due_date' => '2026-11-01', 'status' => InstallmentStatus::Invoiced, 'sort_order' => 1]);
    $invoice = ($this->invoice)('1050.00', ['installment_id' => $installment->id]);

    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['amount' => '1050', 'allocations' => [$invoice->id => '1050']]));

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($installment->fresh()->status)->toBe(InstallmentStatus::Collected);
});

it('splits one receipt over two invoices', function () {
    $first = ($this->invoice)('300.00');
    $second = ($this->invoice)('700.00');

    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['amount' => '1000', 'allocations' => [$first->id => '300', $second->id => '700']]));

    expect($first->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($second->fresh()->status)->toBe(InvoiceStatus::Paid);
});

it('keeps an advance and settles it later', function () {
    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['amount' => '2000']));
    $receipt = Receipt::query()->firstOrFail();

    expect($receipt->unallocated())->toBe('2000.00')
        ->and($this->project->fresh()->collected_total)->toBe('2000.00');

    $invoice = ($this->invoice)('1050.00');
    actingAs($this->user)->put(route('receipts.allocations.update', $receipt), ['allocations' => [$invoice->id => '1050']])->assertRedirect();

    expect($receipt->fresh()->unallocated())->toBe('950.00')
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($this->project->fresh()->collected_total)->toBe('2000.00');
});

it('refuses more than the invoice remaining or the receipt amount', function () {
    $invoice = ($this->invoice)('1050.00', ['paid_amount' => '1000.00', 'status' => 'partially_paid']);
    $other = ($this->invoice)('5000.00');

    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['amount' => '500', 'allocations' => [$invoice->id => '100']]))->assertSessionHasErrors('allocations');
    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['amount' => '500', 'allocations' => [$other->id => '600']]))->assertSessionHasErrors('allocations');

    expect(Receipt::query()->count())->toBe(0);
});

it('refuses another client invoice and drafts', function () {
    $foreign = Invoice::factory()->for(Project::factory())->issued()->create();
    $draft = Invoice::factory()->for($this->project)->create();

    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['allocations' => [$foreign->id => '100']]))->assertSessionHasErrors('allocations');
    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['allocations' => [$draft->id => '100']]))->assertSessionHasErrors('allocations');
});

it('cancelling restores invoice, installment and project collected total', function () {
    $installment = $this->project->installments()->create(['label_ar' => 'دفعة', 'amount' => '1050.00', 'due_type' => 'date', 'due_date' => '2026-11-01', 'status' => InstallmentStatus::Invoiced, 'sort_order' => 1]);
    $invoice = ($this->invoice)('1050.00', ['installment_id' => $installment->id]);
    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['amount' => '1050', 'allocations' => [$invoice->id => '1050']]));
    $receipt = Receipt::query()->firstOrFail();

    actingAs($this->user)->patch(route('receipts.cancel', $receipt), ['reason' => 'شيك مرتجع'])->assertRedirect();

    expect($receipt->fresh()->status)->toBe(ReceiptStatus::Cancelled)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->fresh()->paid_amount)->toBe('0.00')
        ->and($installment->fresh()->status)->toBe(InstallmentStatus::Invoiced)
        ->and($this->project->fresh()->collected_total)->toBe('0.00');
});

it('a receipt without a project counts for the project of the invoice it pays', function () {
    $invoice = ($this->invoice)('1050.00');

    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['project_id' => null, 'amount' => '400', 'allocations' => [$invoice->id => '400']]));

    expect($this->project->fresh()->collected_total)->toBe('400.00');
});

it('cannot delete a receipt and needs receipts.cancel to cancel it', function () {
    actingAs($this->user)->post(route('receipts.store'), ($this->payload)());
    $receipt = Receipt::query()->firstOrFail();

    expect(Route::has('receipts.destroy'))->toBeFalse();
    actingAs(scopedUser(['receipts.view', 'receipts.update']))->patch(route('receipts.cancel', $receipt), ['reason' => 'x'])->assertForbidden();
});

it('an invoice with receipts cannot be cancelled until the receipt is', function () {
    $invoice = ($this->invoice)('1050.00');
    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['allocations' => [$invoice->id => '500']]));
    $canceller = scopedUser(['invoices.view', 'invoices.cancel']);

    actingAs($canceller)->patch(route('invoices.cancel', $invoice), ['reason' => 'خطأ'])->assertSessionHas('error');
    actingAs($this->user)->patch(route('receipts.cancel', Receipt::query()->firstOrFail()), ['reason' => 'خطأ']);
    actingAs($canceller)->patch(route('invoices.cancel', $invoice), ['reason' => 'خطأ']);

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::Cancelled);
});

it('renders the receipt PDF and shares it', function () {
    actingAs($this->user)->post(route('receipts.store'), ($this->payload)());
    $receipt = Receipt::query()->firstOrFail();

    actingAs($this->user)->get(route('receipts.pdf', [$receipt, 'lang' => 'both']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    actingAs($this->user)->postJson(route('receipts.share', $receipt), ['lang' => 'ar'])->assertOk()->assertJsonStructure(['url', 'whatsapp']);
});

it('filters by method, received by and amount', function () {
    $transfer = Lookup::query()->ofType(LookupType::PaymentMethod)->where('code', 'transfer')->value('id');
    $other = scopedUser(['receipts.view']);
    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['amount' => '100']));
    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['amount' => '900', 'payment_method_id' => $transfer, 'received_by' => $other->id]));

    actingAs($this->user);

    expect(Livewire::test(ReceiptsTable::class)->set('filters.payment_method_id', (string) $transfer)->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(ReceiptsTable::class)->set('filters.received_by', (string) $other->id)->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(ReceiptsTable::class)->set('filters.amount', ['min' => '500'])->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(ReceiptsTable::class)->viewData('totals')['amount'])->toBe('1,000.00');
});

it('engineer sees receipts of own projects only', function () {
    $engineer = scopedUser(['receipts.view', 'projects.view'], 'assigned');
    $own = Project::factory()->create(['manager_id' => $engineer->id]);
    actingAs($this->user)->post(route('receipts.store'), ($this->payload)());
    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['client_id' => $own->client_id, 'project_id' => $own->id]));

    actingAs($engineer);
    expect(Livewire::test(ReceiptsTable::class)->viewData('rows')->total())->toBe(1);
    actingAs($engineer)->get(route('receipts.show', Receipt::query()->where('project_id', $this->project->id)->firstOrFail()))->assertNotFound();
});

it('lists receipts on the invoice page and the project tab', function () {
    $invoice = ($this->invoice)('1050.00');
    actingAs($this->user)->post(route('receipts.store'), ($this->payload)(['allocations' => [$invoice->id => '500']]));
    $viewer = scopedUser(['receipts.view', 'invoices.view', 'projects.view']);

    actingAs($viewer)->get(route('invoices.show', $invoice))->assertOk()->assertSee('RV-2026-0001');
    actingAs($viewer)->get(route('projects.show', [$this->project, 'tab' => 'invoices']))->assertOk()->assertSee('RV-2026-0001');
});
