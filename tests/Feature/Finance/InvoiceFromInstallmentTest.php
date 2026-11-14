<?php

use App\Enums\InstallmentStatus;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\User;
use App\Providers\FinanceServiceProvider;
use App\Services\DueTasks;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->user = scopedUser(['invoices.view', 'invoices.create', 'invoices.update', 'invoices.cancel', 'projects.view']);
    $this->project = Project::factory()->create(['vat_rate' => '5.00', 'contract_value' => '100000.00']);
    $this->installment = fn (array $attributes = []) => $this->project->installments()->create($attributes + [
        'label_ar' => 'دفعة مقدمة', 'label_en' => 'Advance payment', 'percent' => '10', 'amount' => '10000.01',
        'due_type' => 'date', 'due_date' => '2026-11-10', 'status' => InstallmentStatus::Due, 'sort_order' => 1,
    ]);
});

it('creates a draft invoice from a due installment with total equal to the amount', function () {
    $installment = ($this->installment)();

    actingAs($this->user)->post(route('projects.installments.invoice', [$this->project, $installment]))->assertRedirect();

    $invoice = Invoice::query()->with('items')->firstOrFail();

    expect($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->installment_id)->toBe($installment->id)
        ->and($invoice->total)->toBe('10000.01')
        ->and($invoice->subtotal)->toBe('9523.82')
        ->and($invoice->vat_amount)->toBe('476.19')
        ->and($invoice->items->first()->description_ar)->toBe('دفعة مقدمة')
        ->and($invoice->items->first()->description_en)->toBe('Advance payment')
        ->and($invoice->items->first()->line_total)->toBe('9523.82');
});

it('second click opens the same invoice', function () {
    $installment = ($this->installment)();

    actingAs($this->user)->post(route('projects.installments.invoice', [$this->project, $installment]));
    actingAs($this->user)->post(route('projects.installments.invoice', [$this->project, $installment]))
        ->assertRedirect(route('invoices.show', Invoice::query()->firstOrFail()));

    expect(Invoice::query()->count())->toBe(1);
});

it('issuing marks the installment invoiced and cancelling returns it to due', function () {
    $installment = ($this->installment)();
    actingAs($this->user)->post(route('projects.installments.invoice', [$this->project, $installment]));
    $invoice = Invoice::query()->firstOrFail();

    actingAs($this->user)->patch(route('invoices.issue', $invoice));
    expect($installment->fresh()->status)->toBe(InstallmentStatus::Invoiced);

    actingAs($this->user)->patch(route('invoices.cancel', $invoice), ['reason' => 'خطأ']);
    expect($installment->fresh()->status)->toBe(InstallmentStatus::Due);

    // A cancelled invoice no longer blocks a new one for the same installment.
    actingAs($this->user)->post(route('projects.installments.invoice', [$this->project, $installment]));
    expect(Invoice::query()->count())->toBe(2);
});

it('an installment not yet due goes back to not due when its invoice is cancelled', function () {
    $installment = ($this->installment)(['due_date' => '2026-12-31', 'status' => InstallmentStatus::NotDue]);
    $invoice = Invoice::factory()->for($this->project)->create(['installment_id' => $installment->id]);

    actingAs($this->user)->patch(route('invoices.issue', $invoice));
    actingAs($this->user)->patch(route('invoices.cancel', $invoice), ['reason' => 'خطأ']);

    expect($installment->fresh()->status)->toBe(InstallmentStatus::NotDue);
});

it('refuses an installment that is not due', function () {
    $installment = ($this->installment)(['due_date' => '2026-12-31', 'status' => InstallmentStatus::NotDue]);

    actingAs($this->user)->post(route('projects.installments.invoice', [$this->project, $installment]))->assertSessionHas('error');

    expect(Invoice::query()->count())->toBe(0);
});

it('needs invoices.create and a visible project', function () {
    $installment = ($this->installment)();

    actingAs(scopedUser(['invoices.view', 'projects.view']))->post(route('projects.installments.invoice', [$this->project, $installment]))->assertForbidden();
    actingAs(scopedUser(['invoices.create', 'projects.view'], 'assigned'))->post(route('projects.installments.invoice', [$this->project, $installment]))->assertNotFound();
});

it('shows the create invoice button on due installments', function () {
    ($this->installment)();

    actingAs($this->user)->get(route('projects.show', [$this->project, 'tab' => 'payments']))
        ->assertOk()->assertSee(__('finance.invoices.create_from_installment'));
});

it('notifies overdue invoices and installments once', function () {
    DueTasks::flush();
    FinanceServiceProvider::registerDueTasks();
    $accountant = User::factory()->create()->assignRole('accountant');
    ($this->installment)(['due_date' => '2026-11-01']);
    Invoice::factory()->for($this->project)->issued()->create(['due_date' => '2026-11-10']);
    Invoice::factory()->for($this->project)->issued()->create(['due_date' => '2026-11-30']);

    app(DueTasks::class)->runDue();
    $this->travel(1)->days();
    app(DueTasks::class)->runDue();

    expect($accountant->notifications()->where('type', 'invoice_overdue')->count())->toBe(1)
        ->and($accountant->notifications()->where('type', 'installment_overdue')->count())->toBe(1);
});
