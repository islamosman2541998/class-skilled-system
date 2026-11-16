<?php

use App\Enums\ClaimStatus;
use App\Enums\InvoiceStatus;
use App\Models\Claim;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectHandover;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['projects.view', 'claims.view', 'invoices.view', 'invoices.create']);
    $this->project = Project::factory()->create(['billing_method' => 'claims', 'vat_rate' => '5.00', 'status' => 'delivered']);
    Claim::factory()->for($this->project)->create(['client_id' => $this->project->client_id, 'status' => ClaimStatus::Invoiced, 'retention_amount' => '1000.00']);
    Claim::factory()->for($this->project)->create(['client_id' => $this->project->client_id, 'status' => ClaimStatus::Approved, 'retention_amount' => '500.50']);
    Claim::factory()->for($this->project)->create(['client_id' => $this->project->client_id, 'status' => ClaimStatus::Submitted, 'retention_amount' => '999.00']);
});

function handOver(Project $project): void
{
    $handover = new ProjectHandover(['handover_date' => '2026-11-10', 'warranty_months' => 12]);
    $handover->forceFill(['project_id' => $project->id, 'warranty_end' => '2027-11-10'])->save();
}

it('creates one retention invoice after handover for the retained amount', function () {
    handOver($this->project);

    actingAs($this->user)->post(route('projects.retention-invoice', $this->project))->assertRedirect();

    $invoice = Invoice::query()->with('items')->sole();

    expect($invoice->retention_release)->toBeTrue()
        ->and($invoice->status)->toBe(InvoiceStatus::Draft)
        ->and($invoice->subtotal)->toBe('1500.50')
        ->and($invoice->vat_amount)->toBe('75.03')
        ->and($invoice->total)->toBe('1575.53')
        ->and($invoice->items[0]->description_ar)->toContain($this->project->number);
});

it('a second click returns the same invoice', function () {
    handOver($this->project);

    actingAs($this->user)->post(route('projects.retention-invoice', $this->project));
    actingAs($this->user)->post(route('projects.retention-invoice', $this->project));

    expect(Invoice::query()->count())->toBe(1);
});

it('refused before handover and when nothing is retained', function () {
    actingAs($this->user)->post(route('projects.retention-invoice', $this->project))->assertSessionHas('error', __('site.retention.no_handover'));

    $empty = Project::factory()->create(['billing_method' => 'claims']);
    handOver($empty);
    actingAs($this->user)->post(route('projects.retention-invoice', $empty))->assertSessionHas('error', __('site.retention.nothing'));

    expect(Invoice::query()->count())->toBe(0);
});

it('needs invoices.create', function () {
    handOver($this->project);

    actingAs(scopedUser(['projects.view', 'claims.view', 'invoices.view']))->post(route('projects.retention-invoice', $this->project))->assertForbidden();
});
