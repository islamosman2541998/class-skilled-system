<?php

use App\Enums\PurchaseStatus;
use App\Livewire\Tables\PurchasesTable;
use App\Livewire\Tables\Reports\VatSummaryReport;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Uploads\TemporaryUpload;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['projects.view', 'suppliers.view', 'purchases.view', 'purchases.create', 'purchases.update', 'purchases.delete']);
    $this->project = Project::factory()->create();
    $this->supplier = Supplier::factory()->create();
});

function purchasePayload(array $overrides = []): array
{
    return array_merge([
        'supplier_id' => test()->supplier->id,
        'project_id' => test()->project->id,
        'purchase_date' => '2026-11-14',
        'supplier_invoice_no' => 'INV-5521',
        'vat' => '1',
        'items' => [
            ['description_ar' => 'أسمنت', 'quantity' => '10', 'unit_price' => '150'],
            ['description_ar' => 'رمل', 'quantity' => '2', 'unit_price' => '250.50'],
        ],
    ], $overrides);
}

it('records a purchase invoice with two lines and VAT and the project cost rises by the subtotal', function () {
    actingAs($this->user)->post(route('purchases.store'), purchasePayload())->assertRedirect();

    $purchase = Purchase::query()->with('items')->sole();

    expect($purchase->number)->toBe('PB-2026-0001')
        ->and($purchase->items)->toHaveCount(2)
        ->and($purchase->subtotal)->toBe('2001.00')
        ->and($purchase->vat_rate)->toBe('5.00')
        ->and($purchase->vat_amount)->toBe('100.05')
        ->and($purchase->total)->toBe('2101.05')
        ->and($purchase->paid_amount)->toBe('0.00')
        ->and($purchase->status)->toBe(PurchaseStatus::Unpaid)
        ->and($this->project->fresh()->cost_total)->toBe('2001.00');

    actingAs($this->user)->get(route('purchases.show', $purchase))->assertOk()->assertSee('INV-5521');
});

it('a non-registered supplier invoice has no VAT', function () {
    actingAs($this->user)->post(route('purchases.store'), purchasePayload(['vat' => '0']))->assertRedirect();

    $purchase = Purchase::query()->sole();
    expect($purchase->vat_rate)->toBe('0.00')->and($purchase->vat_amount)->toBe('0.00')->and($purchase->total)->toBe('2001.00');
});

it('refuses the same supplier invoice number twice for one supplier', function () {
    actingAs($this->user)->post(route('purchases.store'), purchasePayload())->assertRedirect();
    actingAs($this->user)->post(route('purchases.store'), purchasePayload())->assertSessionHasErrors('supplier_invoice_no');
    actingAs($this->user)->post(route('purchases.store'), purchasePayload(['supplier_id' => Supplier::factory()->create()->id]))->assertSessionHasNoErrors();

    expect(Purchase::query()->count())->toBe(2);
});

it('editing amounts updates the project cost; deleting removes it', function () {
    actingAs($this->user)->post(route('purchases.store'), purchasePayload());
    $purchase = Purchase::query()->sole();

    actingAs($this->user)->put(route('purchases.update', $purchase), purchasePayload(['items' => [['description_ar' => 'أسمنت', 'quantity' => '4', 'unit_price' => '150']]]))
        ->assertRedirect(route('purchases.show', $purchase));
    expect($this->project->fresh()->cost_total)->toBe('600.00')->and($purchase->fresh()->total)->toBe('630.00');

    $other = Project::factory()->create();
    actingAs($this->user)->put(route('purchases.update', $purchase), purchasePayload(['project_id' => $other->id, 'items' => [['description_ar' => 'أسمنت', 'quantity' => '4', 'unit_price' => '150']]]));
    expect($this->project->fresh()->cost_total)->toBe('0.00')->and($other->fresh()->cost_total)->toBe('600.00');

    actingAs($this->user)->delete(route('purchases.destroy', $purchase))->assertRedirect();
    $this->assertSoftDeleted($purchase);
    expect($other->fresh()->cost_total)->toBe('0.00');
});

it('VAT summary input VAT includes purchases of the month', function () {
    actingAs($this->user)->post(route('purchases.store'), purchasePayload());

    actingAs(scopedUser(['reports.view', 'invoices.view', 'expenses.view', 'purchases.view']));
    $row = Livewire::test(VatSummaryReport::class)->viewData('rows')->firstWhere('month', '2026-11');

    expect(number_format((float) $row->getAttribute('purchases_net'), 2, '.', ''))->toBe('2001.00')
        ->and(number_format((float) $row->getAttribute('input_vat'), 2, '.', ''))->toBe('100.05');
});

it('engineer sees purchases of own projects only', function () {
    $engineer = scopedUser(['projects.view', 'purchases.view', 'purchases.create'], 'assigned');
    $own = Project::factory()->create(['manager_id' => $engineer->id]);
    actingAs($this->user)->post(route('purchases.store'), purchasePayload());
    actingAs($engineer)->post(route('purchases.store'), purchasePayload(['project_id' => $own->id, 'supplier_invoice_no' => 'A-1']))->assertRedirect();
    actingAs($engineer)->post(route('purchases.store'), purchasePayload(['supplier_invoice_no' => 'A-2']))->assertSessionHasErrors('project_id');

    expect(Livewire::test(PurchasesTable::class)->viewData('rows')->total())->toBe(1);
    actingAs($engineer)->get(route('purchases.show', Purchase::query()->where('project_id', $this->project->id)->sole()))->assertNotFound();
});

it('stores the supplier invoice attachment', function () {
    Storage::fake('private');
    $file = UploadedFile::fake()->image('invoice.jpg', 800, 600);
    $bytes = file_get_contents($file->getPathname());
    $upload = TemporaryUpload::create($this->user->id, strlen($bytes), 'invoice.jpg');
    file_put_contents($upload->path(), $bytes);
    $upload->saveMeta(['offset' => strlen($bytes)]);

    actingAs($this->user)->post(route('purchases.store'), purchasePayload(['uploads' => [$upload->id]]))->assertRedirect();

    expect(Purchase::query()->sole()->getMedia('attachments'))->toHaveCount(1);
});
