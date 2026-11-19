<?php

use App\Actions\Resources\BillSubcontractPayment;
use App\Enums\SubcontractStatus;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Subcontract;
use App\Models\Supplier;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['projects.view', 'suppliers.view', 'subcontracts.view', 'subcontracts.create', 'subcontracts.update', 'purchases.view', 'purchases.create']);
    $this->project = Project::factory()->create();
    $this->sub = Supplier::factory()->subcontractor()->create(['name_ar' => 'مقاولات الريم للجبس']);
});

function subcontractPayload(array $overrides = []): array
{
    return array_merge([
        'supplier_id' => test()->sub->id,
        'project_id' => test()->project->id,
        'scope' => 'أعمال الجبس والأسقف المعلقة للدورين',
        'value' => '30000',
        'vat' => '1',
        'start_date' => '2026-11-01',
        'end_date' => '2027-01-31',
        'payments' => [
            ['label_ar' => 'دفعة مقدمة', 'amount' => '6000', 'due_date' => '2026-11-05'],
            ['label_ar' => 'عند إنجاز الدور الأرضي', 'amount' => '12000'],
            ['label_ar' => 'عند التسليم', 'amount' => '12000'],
        ],
    ], $overrides);
}

it('creates a subcontract with three payment lines within its value', function () {
    actingAs($this->user)->post(route('subcontracts.store'), subcontractPayload())->assertRedirect();

    $subcontract = Subcontract::query()->with('payments')->sole();

    expect($subcontract->number)->toBe('SC-2026-0001')
        ->and($subcontract->status)->toBe(SubcontractStatus::Active)
        ->and($subcontract->value)->toBe('30000.00')
        ->and($subcontract->vat_rate)->toBe('5.00')
        ->and($subcontract->payments)->toHaveCount(3)
        ->and($subcontract->payments[0]->amount)->toBe('6000.00');

    actingAs($this->user)->get(route('subcontracts.show', $subcontract))->assertOk()->assertSee('مقاولات الريم للجبس');
});

it('refuses payment lines above the value and a supplier that is not a subcontractor', function () {
    actingAs($this->user)->post(route('subcontracts.store'), subcontractPayload(['value' => '20000']))->assertSessionHasErrors('payments');
    actingAs($this->user)->post(route('subcontracts.store'), subcontractPayload(['supplier_id' => Supplier::factory()->create()->id]))->assertSessionHasErrors('supplier_id');

    expect(Subcontract::query()->count())->toBe(0);
});

it('billing a line creates one purchase invoice and a second click returns it', function () {
    actingAs($this->user)->post(route('subcontracts.store'), subcontractPayload());
    $payment = Subcontract::query()->sole()->payments()->orderBy('sort_order')->firstOrFail();

    actingAs($this->user)->post(route('subcontracts.bill', [$payment->subcontract_id, $payment]))->assertRedirect();
    actingAs($this->user)->post(route('subcontracts.bill', [$payment->subcontract_id, $payment]))->assertRedirect();

    $purchase = Purchase::query()->sole();

    expect($purchase->subcontract_id)->toBe($payment->subcontract_id)
        ->and($purchase->subcontract_payment_id)->toBe($payment->id)
        ->and($purchase->supplier_id)->toBe($this->sub->id)
        ->and($purchase->subtotal)->toBe('6000.00')
        ->and($purchase->vat_amount)->toBe('300.00')
        ->and($purchase->total)->toBe('6300.00')
        ->and(app(BillSubcontractPayment::class)->handle($payment->fresh(), $this->user)->id)->toBe($purchase->id)
        ->and($payment->subcontract->fresh()->billed())->toBe('6000.00')
        ->and($payment->subcontract->fresh()->remaining())->toBe('24000.00');
});

it('a billed line cannot be changed or removed', function () {
    actingAs($this->user)->post(route('subcontracts.store'), subcontractPayload());
    $subcontract = Subcontract::query()->sole();
    $payments = $subcontract->payments()->orderBy('sort_order')->get();
    app(BillSubcontractPayment::class)->handle($payments[0], $this->user);

    $changed = subcontractPayload(['payments' => [
        ['id' => $payments[0]->id, 'label_ar' => 'دفعة مقدمة', 'amount' => '7000'],
        ['id' => $payments[1]->id, 'label_ar' => 'عند إنجاز الدور الأرضي', 'amount' => '12000'],
    ]]);
    actingAs($this->user)->put(route('subcontracts.update', $subcontract), $changed)->assertSessionHasErrors('payments');

    $removed = subcontractPayload(['payments' => [
        ['id' => $payments[1]->id, 'label_ar' => 'عند إنجاز الدور الأرضي', 'amount' => '12000'],
    ]]);
    actingAs($this->user)->put(route('subcontracts.update', $subcontract), $removed)->assertSessionHasErrors('payments');

    $kept = subcontractPayload(['payments' => [
        ['id' => $payments[0]->id, 'label_ar' => 'دفعة مقدمة', 'amount' => '6000'],
        ['id' => $payments[1]->id, 'label_ar' => 'الدور الأرضي', 'amount' => '24000'],
    ]]);
    actingAs($this->user)->put(route('subcontracts.update', $subcontract), $kept)->assertSessionHasNoErrors();

    expect($subcontract->payments()->count())->toBe(2)
        ->and($subcontract->payments()->whereKey($payments[1]->id)->value('amount'))->toBe('24000.00');
});

it('subcontract bills count as project cost', function () {
    actingAs($this->user)->post(route('subcontracts.store'), subcontractPayload());
    app(BillSubcontractPayment::class)->handle(Subcontract::query()->sole()->payments()->orderBy('sort_order')->firstOrFail(), $this->user);

    expect($this->project->fresh()->cost_total)->toBe('6000.00')
        ->and(Purchase::query()->whereNotNull('subcontract_id')->sum('subtotal'))->toEqual(6000);
});

it('cancelled subcontract cannot be billed', function () {
    actingAs($this->user)->post(route('subcontracts.store'), subcontractPayload(['status' => 'cancelled']));
    $subcontract = Subcontract::query()->sole();
    $subcontract->forceFill(['status' => SubcontractStatus::Cancelled])->save();
    $payment = $subcontract->payments()->firstOrFail();

    actingAs($this->user)->post(route('subcontracts.bill', [$subcontract, $payment]))->assertSessionHas('error');

    expect(Purchase::query()->count())->toBe(0);
});

it('a deleted bill frees its line to be billed again', function () {
    actingAs($this->user)->post(route('subcontracts.store'), subcontractPayload());
    $payment = Subcontract::query()->sole()->payments()->orderBy('sort_order')->firstOrFail();
    $first = app(BillSubcontractPayment::class)->handle($payment, $this->user);

    $first->delete();
    $second = app(BillSubcontractPayment::class)->handle($payment->fresh(), $this->user);

    expect($second->id)->not->toBe($first->id)
        ->and($second->subcontract_payment_id)->toBe($payment->id)
        ->and(Purchase::query()->count())->toBe(1)
        ->and($this->project->fresh()->cost_total)->toBe('6000.00');
});

it('renders the subcontract list and project tab with calculated totals', function () {
    actingAs($this->user)->post(route('subcontracts.store'), subcontractPayload())->assertRedirect();

    actingAs($this->user)->get(route('subcontracts.index'))
        ->assertOk()
        ->assertSee('SC-2026-0001');

    actingAs($this->user)
        ->get(route('projects.show', [$this->project, 'tab' => 'purchases']))
        ->assertOk()
        ->assertSee(__('projects.tabs.purchases'))
        ->assertDontSee('projects.tabs.', false);

    foreach (['daily_reports', 'claims', 'purchases', 'resources'] as $tab) {
        expect(__('projects.tabs.'.$tab, [], 'ar'))->not->toBe('projects.tabs.'.$tab)
            ->and(__('projects.tabs.'.$tab, [], 'en'))->not->toBe('projects.tabs.'.$tab);
    }
});
