<?php

use App\Enums\DocumentType;
use App\Enums\InvoiceStatus;
use App\Livewire\Tables\InvoicesTable;
use App\Models\Invoice;
use App\Models\Project;
use App\Search\SearchRegistry;
use App\Services\NumberGenerator;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->user = scopedUser(['invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete', 'invoices.cancel', 'projects.view']);
    $this->project = Project::factory()->create(['vat_rate' => '5.00', 'contract_value' => '100000.00']);
    $this->payload = fn (array $extra = []) => $extra + [
        'project_id' => $this->project->id,
        'issue_date' => '2026-11-16',
        'due_date' => '2026-12-16',
        'items' => [['description_ar' => 'دفعة مقدمة', 'quantity' => '1', 'unit_price' => '10000']],
    ];
});

it('creates a draft without a number', function () {
    actingAs($this->user)->post(route('invoices.store'), ($this->payload)())->assertRedirect();

    $invoice = Invoice::query()->firstOrFail();

    expect($invoice->number)->toBeNull()
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->client_id)->toBe($this->project->client_id)
        ->and($invoice->vat_rate)->toBe('5.00')
        ->and($invoice->subtotal)->toBe('10000.00')
        ->and($invoice->vat_amount)->toBe('500.00')
        ->and($invoice->total)->toBe('10500.00')
        ->and($invoice->items)->toHaveCount(1);
});

it('issues with INV-2026-0001 and locks editing', function () {
    $invoice = Invoice::factory()->for($this->project)->create();

    actingAs($this->user)->patch(route('invoices.issue', $invoice))->assertRedirect();
    $invoice->refresh();

    expect($invoice->number)->toBe('INV-2026-0001')
        ->and($invoice->status)->toBe(InvoiceStatus::Issued)
        ->and($invoice->issued_by)->toBe($this->user->id);

    actingAs($this->user)->get(route('invoices.edit', $invoice))->assertRedirect(route('invoices.show', $invoice));
    actingAs($this->user)->put(route('invoices.update', $invoice), ($this->payload)(['items' => [['description_ar' => 'x', 'quantity' => '1', 'unit_price' => '1']]]));

    expect($invoice->fresh()->total)->toBe($invoice->total);
});

it('issuing twice keeps one number', function () {
    $invoice = Invoice::factory()->for($this->project)->create();

    actingAs($this->user)->patch(route('invoices.issue', $invoice));
    actingAs($this->user)->patch(route('invoices.issue', $invoice));

    expect($invoice->fresh()->number)->toBe('INV-2026-0001')
        ->and(app(NumberGenerator::class)->preview(DocumentType::Invoice))->toBe('INV-2026-0002');
});

it('cancels with a reason', function () {
    $invoice = Invoice::factory()->for($this->project)->issued()->create();

    actingAs($this->user)->patch(route('invoices.cancel', $invoice), [])->assertSessionHasErrors('reason');
    actingAs($this->user)->patch(route('invoices.cancel', $invoice), ['reason' => 'خطأ في المبلغ'])->assertRedirect();

    $invoice->refresh();
    expect($invoice->status)->toBe(InvoiceStatus::Cancelled)
        ->and($invoice->cancel_reason)->toBe('خطأ في المبلغ')
        ->and($invoice->cancelled_by)->toBe($this->user->id);
});

it('refuses cancelling with active allocations', function () {
    $invoice = Invoice::factory()->for($this->project)->issued()->create(['paid_amount' => '100.00', 'status' => 'partially_paid']);

    actingAs($this->user)->patch(route('invoices.cancel', $invoice), ['reason' => 'خطأ'])->assertSessionHas('error');

    expect($invoice->fresh()->status)->toBe(InvoiceStatus::PartiallyPaid);
});

it('needs invoices.cancel to cancel', function () {
    $invoice = Invoice::factory()->for($this->project)->issued()->create();

    actingAs(scopedUser(['invoices.view', 'invoices.update']))->patch(route('invoices.cancel', $invoice), ['reason' => 'x'])->assertForbidden();
});

it('replace cancels and opens a draft copy', function () {
    $invoice = Invoice::factory()->for($this->project)->issued()->create();
    $invoice->items()->create(['description_ar' => 'بند', 'quantity' => '2', 'unit_price' => '50', 'line_total' => '100.00', 'sort_order' => 1]);

    $response = actingAs($this->user)->patch(route('invoices.cancel', $invoice), ['reason' => 'تعديل البنود', 'replace' => '1']);

    $copy = Invoice::query()->where('replaces_id', $invoice->id)->firstOrFail();
    $response->assertRedirect(route('invoices.edit', $copy));

    expect($copy->status)->toBe(InvoiceStatus::Draft)
        ->and($copy->number)->toBeNull()
        ->and($copy->items)->toHaveCount(1)
        ->and($invoice->fresh()->status)->toBe(InvoiceStatus::Cancelled);
});

it('deletes drafts only', function () {
    $draft = Invoice::factory()->for($this->project)->create();
    $issued = Invoice::factory()->for($this->project)->issued()->create();

    actingAs($this->user)->delete(route('invoices.destroy', $draft))->assertRedirect(route('invoices.index'));
    actingAs($this->user)->delete(route('invoices.destroy', $issued))->assertSessionHas('error');

    expect(Invoice::query()->find($draft->id))->toBeNull()
        ->and(Invoice::withTrashed()->find($draft->id)->trashed())->toBeTrue()
        ->and(Invoice::query()->find($issued->id))->not->toBeNull();
});

it('issued invoices raise project invoiced totals and cancelling lowers them', function () {
    $invoice = Invoice::factory()->for($this->project)->create(['subtotal' => '10000.00', 'vat_amount' => '500.00', 'total' => '10500.00']);
    Invoice::factory()->for($this->project)->create(['total' => '999.00']);

    actingAs($this->user)->patch(route('invoices.issue', $invoice));
    $this->project->refresh();

    expect($this->project->invoiced_total)->toBe('10500.00')
        ->and($this->project->invoiced_net)->toBe('10000.00');

    actingAs($this->user)->patch(route('invoices.cancel', $invoice), ['reason' => 'خطأ']);

    expect($this->project->fresh()->invoiced_total)->toBe('0.00');
});

it('engineer sees invoices of own projects only', function () {
    $engineer = scopedUser(['invoices.view', 'projects.view'], 'assigned');
    $own = Project::factory()->create(['manager_id' => $engineer->id]);
    $mine = Invoice::factory()->for($own)->issued()->create();
    $other = Invoice::factory()->for($this->project)->issued()->create();

    actingAs($engineer)->get(route('invoices.show', $mine))->assertOk();
    actingAs($engineer)->get(route('invoices.show', $other))->assertNotFound();
    expect(Livewire::test(InvoicesTable::class)->viewData('rows')->total())->toBe(1);

    $urls = collect(app(SearchRegistry::class)->search('INV', $engineer))->pluck('results')->flatten(1)->pluck('url');
    expect($urls->all())->toContain(route('invoices.show', $mine))->not->toContain(route('invoices.show', $other));

    actingAs($engineer)->post(route('invoices.store'), ($this->payload)())->assertForbidden();
});

it('cannot invoice a project the user does not see', function () {
    $creator = scopedUser(['invoices.view', 'invoices.create'], 'assigned');

    actingAs($creator)->post(route('invoices.store'), ($this->payload)())->assertSessionHasErrors('project_id');
});

it('filters overdue and due date range', function () {
    Invoice::factory()->for($this->project)->issued()->create(['due_date' => '2026-11-10', 'total' => '100.00']);
    Invoice::factory()->for($this->project)->issued()->create(['due_date' => '2026-11-20', 'total' => '200.00']);
    Invoice::factory()->for($this->project)->create(['due_date' => '2026-11-01', 'total' => '300.00']);

    actingAs($this->user);

    expect(Livewire::test(InvoicesTable::class)->set('filters.overdue', '1')->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(InvoicesTable::class)->set('filters.due_date', ['from' => '2026-11-15', 'to' => '2026-11-30'])->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(InvoicesTable::class)->viewData('totals')['total'])->toBe('600.00');
});

it('shows the invoices tab on the project and client pages', function () {
    $invoice = Invoice::factory()->for($this->project)->issued()->create();
    $viewer = scopedUser(['invoices.view', 'projects.view', 'clients.view']);

    actingAs($viewer)->get(route('projects.show', [$this->project, 'tab' => 'invoices']))->assertOk()->assertSee($invoice->number);
    actingAs($viewer)->get(route('clients.show', [$this->project->client_id, 'tab' => 'invoices']))->assertOk();
});
