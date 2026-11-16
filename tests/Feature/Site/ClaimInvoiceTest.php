<?php

use App\Actions\Finance\CancelInvoice;
use App\Actions\Finance\IssueInvoice;
use App\Actions\Site\ApproveClaim;
use App\Actions\Site\SaveClaim;
use App\Actions\Site\StartClaim;
use App\Actions\Site\SubmitClaim;
use App\Enums\ClaimStatus;
use App\Enums\InvoiceStatus;
use App\Models\Claim;
use App\Models\Invoice;
use App\Models\Project;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['projects.view', 'claims.view', 'claims.create', 'claims.update', 'claims.approve', 'invoices.view', 'invoices.update', 'invoices.cancel']);
    $this->project = Project::factory()->create([
        'manager_id' => $this->user->id, 'billing_method' => 'claims', 'status' => 'in_progress', 'start_date' => '2026-11-01',
        'vat_rate' => '5.00', 'retention_percent' => '10.00', 'contract_discount_percent' => '5.0000', 'advance_amount' => '5000',
    ]);
    $this->area = $this->project->items()->create(['description_ar' => 'بلاط', 'quantity' => '100', 'unit_price' => '50', 'line_total' => '5000', 'sort_order' => 1]);
    $this->lump = $this->project->items()->create(['description_ar' => 'تكييف', 'quantity' => '1', 'unit_price' => '20000', 'line_total' => '20000', 'sort_order' => 2]);
});

/** A submitted claim with 30 m² and 50% of the lump sum. */
function submittedClaim(): Claim
{
    $claim = app(StartClaim::class)->handle(test()->project, test()->user);
    $lines = $claim->lines->keyBy('project_item_id');
    app(SaveClaim::class)->handle($claim, [
        'period_from' => '2026-11-01', 'period_to' => '2026-11-15',
        'lines' => [
            $lines[test()->area->id]->id => ['current_quantity' => '30'],
            $lines[test()->lump->id]->id => ['cumulative_percent' => '50'],
        ],
    ], test()->user);

    return app(SubmitClaim::class)->handle($claim, test()->user);
}

it('approving creates one draft invoice whose total equals the claim total', function () {
    $claim = submittedClaim();

    actingAs($this->user)->patch(route('claims.approve', $claim))->assertRedirect(route('claims.show', $claim));

    $claim->refresh();
    $invoice = Invoice::query()->with('items')->sole();

    expect($claim->status)->toBe(ClaimStatus::Approved)
        ->and($claim->approved_by)->toBe($this->user->id)
        ->and($claim->net_amount)->toBe('7532.50')
        ->and($invoice->claim_id)->toBe($claim->id)
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->subtotal)->toBe($claim->net_amount)
        ->and($invoice->vat_amount)->toBe($claim->vat_amount)
        ->and($invoice->total)->toBe($claim->total)
        ->and($invoice->total)->toBe('7909.13')
        ->and($invoice->due_date->toDateString())->toBe('2026-11-30')
        ->and($invoice->items)->toHaveCount(1)
        ->and($invoice->items[0]->description_ar)->toContain('PC-2026-0001')
        ->and($claim->invoice->id)->toBe($invoice->id);
});

it('approving twice returns the same invoice', function () {
    $claim = submittedClaim();

    $first = app(ApproveClaim::class)->handle($claim, $this->user);
    $second = app(ApproveClaim::class)->handle($claim, $this->user);
    actingAs($this->user)->patch(route('claims.approve', $claim))->assertRedirect();

    expect($second->id)->toBe($first->id)->and(Invoice::query()->count())->toBe(1);
});

it('approve needs claims.approve and a submitted claim', function () {
    $claim = submittedClaim();
    actingAs(scopedUser(['projects.view', 'claims.view', 'claims.update']))->patch(route('claims.approve', $claim))->assertForbidden();

    $draft = Claim::factory()->create();
    actingAs($this->user)->patch(route('claims.approve', $draft))->assertSessionHas('error');
    expect(Invoice::query()->count())->toBe(0);
});

it('issuing the invoice marks the claim invoiced; cancelling returns it to approved and the replacement keeps claim_id', function () {
    $claim = submittedClaim();
    $invoice = app(ApproveClaim::class)->handle($claim, $this->user);

    app(IssueInvoice::class)->handle($invoice, $this->user);
    expect($claim->fresh()->status)->toBe(ClaimStatus::Invoiced);

    $replacement = app(CancelInvoice::class)->handle($invoice->fresh(), 'خطأ في التاريخ', $this->user, true);

    expect($claim->fresh()->status)->toBe(ClaimStatus::Approved)
        ->and($replacement->claim_id)->toBe($claim->id)
        ->and($claim->fresh()->invoice->id)->toBe($replacement->id)
        ->and(app(ApproveClaim::class)->handle($claim->fresh(), $this->user)->id)->toBe($replacement->id);

    app(IssueInvoice::class)->handle($replacement, $this->user);
    expect($claim->fresh()->status)->toBe(ClaimStatus::Invoiced);
});

it('the next claim takes previous from an invoiced claim', function () {
    $claim = submittedClaim();
    app(IssueInvoice::class)->handle(app(ApproveClaim::class)->handle($claim, $this->user), $this->user);

    $next = app(StartClaim::class)->handle($this->project, $this->user);

    expect($next->lines()->where('project_item_id', $this->area->id)->value('previous_quantity'))->toBe('30.000')
        ->and($next->lines()->where('project_item_id', $this->lump->id)->value('previous_quantity'))->toBe('0.500')
        ->and($next->period_from->toDateString())->toBe('2026-11-16');
});
