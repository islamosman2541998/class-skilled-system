<?php

use App\Enums\LookupType;
use App\Enums\QuotationStatus;
use App\Livewire\Tables\QuotationsTable;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\Quotation;
use App\Settings\FinanceSettings;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-14 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['quotations.view', 'quotations.create', 'quotations.update', 'leads.view']);
    $this->unit = Lookup::query()->ofType(LookupType::Unit)->value('id');
});

function quotationPayload(array $overrides = []): array
{
    return array_replace([
        'client_id' => Client::factory()->create()->id,
        'title_ar' => 'عزل سطح فيلا',
        'issue_date' => '2026-11-14',
        'valid_until' => '2026-12-14',
        'assigned_to' => auth()->id() ?? User::query()->value('id'),
        'discount_type' => 'percent',
        'discount_value' => '10',
        'items' => [
            ['section_ar' => 'أعمال العزل', 'description_ar' => 'عزل مائي', 'unit_id' => Lookup::query()->ofType(LookupType::Unit)->value('id'), 'quantity' => '120.5', 'unit_price' => '45'],
            ['section_ar' => 'أعمال العزل', 'description_ar' => 'طبقة حماية', 'unit_id' => null, 'quantity' => '1', 'unit_price' => '3500'],
        ],
        'payments' => [
            ['label_ar' => 'مقدم', 'percent' => '30'],
            ['label_ar' => 'عند منتصف الأعمال', 'percent' => '40'],
            ['label_ar' => 'عند التسليم', 'percent' => '30'],
        ],
        'terms_ar' => 'الشروط',
    ], $overrides);
}

it('creates a quotation with number QT-2026-0001, items, payments and server totals', function () {
    actingAs($this->user)->post(route('quotations.store'), quotationPayload(['assigned_to' => $this->user->id]))->assertRedirect();

    $q = Quotation::query()->with(['items', 'payments'])->firstOrFail();

    expect($q->number)->toBe('QT-2026-0001')
        ->and($q->status)->toBe(QuotationStatus::Draft)
        ->and($q->vat_rate)->toBe('5.00')
        ->and($q->subtotal)->toBe('8922.50')
        ->and($q->total)->toBe('8431.76')
        ->and($q->items)->toHaveCount(2)
        ->and($q->items[0]->line_total)->toBe('5422.50')
        ->and($q->payments->pluck('amount')->all())->toBe(['2529.53', '3372.70', '2529.53']);
});

it('ignores totals posted by the browser', function () {
    actingAs($this->user)->post(route('quotations.store'), quotationPayload(['assigned_to' => $this->user->id, 'total' => '1', 'subtotal' => '1']));

    expect(Quotation::query()->value('total'))->toBe('8431.76');
});

it('keeps the VAT rate of an existing quotation after the setting changes', function () {
    actingAs($this->user)->post(route('quotations.store'), quotationPayload(['assigned_to' => $this->user->id]));
    $q = Quotation::query()->firstOrFail();

    $finance = app(FinanceSettings::class);
    $finance->vat_rate = '10.00';
    $finance->save();

    actingAs($this->user)->put(route('quotations.update', $q), quotationPayload(['client_id' => $q->client_id, 'assigned_to' => $this->user->id, 'discount_type' => '', 'discount_value' => '0']))->assertRedirect();

    expect($q->fresh()->vat_rate)->toBe('5.00')->and($q->fresh()->vat_amount)->toBe('446.13');
});

it('requires payment percents that sum to 100 and at least one item', function () {
    actingAs($this->user)->post(route('quotations.store'), quotationPayload(['assigned_to' => $this->user->id, 'items' => []]))->assertSessionHasErrors('items');
    actingAs($this->user)->post(route('quotations.store'), quotationPayload(['assigned_to' => $this->user->id, 'payments' => [['label_ar' => 'مقدم', 'percent' => '60']]]))->assertSessionHasErrors('payments');
});

it('rejects a percent discount over 100 and an amount above the subtotal', function () {
    actingAs($this->user)->post(route('quotations.store'), quotationPayload(['assigned_to' => $this->user->id, 'discount_value' => '150']))->assertSessionHasErrors('discount_value');
    actingAs($this->user)->post(route('quotations.store'), quotationPayload(['assigned_to' => $this->user->id, 'discount_type' => 'amount', 'discount_value' => '999999']))->assertSessionHasErrors('discount_value');
    expect(Quotation::query()->count())->toBe(0);
});

it('creates a quotation from a lead, creating the client and moving the lead to quoted', function () {
    $lead = Lead::factory()->create(['contact_name' => 'راشد النقبي', 'contact_phone' => '0503332211']);

    actingAs($this->user)->post(route('quotations.store'), quotationPayload(['lead_id' => $lead->id, 'client_id' => null, 'assigned_to' => $this->user->id]))->assertRedirect();

    $lead->refresh();
    expect($lead->statusCode())->toBe('quoted')
        ->and(Quotation::query()->value('client_id'))->toBe($lead->client_id)
        ->and(Client::query()->find($lead->client_id)->name_ar)->toBe('راشد النقبي');
});

it('shows expired for a sent quotation past its validity and filters it', function () {
    $expired = Quotation::factory()->create(['status' => 'sent', 'valid_until' => '2026-11-10']);
    Quotation::factory()->create(['status' => 'sent', 'valid_until' => '2026-11-30']);
    Quotation::factory()->create(['status' => 'accepted', 'valid_until' => '2026-11-01']);
    actingAs($this->user);

    expect($expired->displayStatus())->toBe(QuotationStatus::Expired)
        ->and(Livewire::test(QuotationsTable::class)->set('filters.status', 'expired')->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(QuotationsTable::class)->set('filters.status', 'sent')->viewData('rows')->total())->toBe(1);
});

it('filters by total range and sums the total column over the filtered result', function () {
    Quotation::factory()->create(['total' => '1000.00']);
    Quotation::factory()->create(['total' => '5000.00']);
    Quotation::factory()->create(['total' => '20000.00']);
    actingAs($this->user);

    $table = Livewire::test(QuotationsTable::class)->set('filters.total', ['min' => '1000', 'max' => '5000']);

    expect($table->viewData('rows')->total())->toBe(2)->and($table->viewData('totals')['total'])->toBe('6,000.00');
});

it('lets an assigned-scope user see only their quotations, also in search', function () {
    $engineer = scopedUser(['quotations.view'], 'assigned');
    $mine = Quotation::factory()->create(['assigned_to' => $engineer->id, 'title_ar' => 'عرض مخصص']);
    $theirs = Quotation::factory()->create(['title_ar' => 'عرض مخصص']);
    actingAs($engineer);

    expect(Livewire::test(QuotationsTable::class)->viewData('rows')->total())->toBe(1);
    $this->get(route('quotations.show', $theirs))->assertNotFound();
    $this->get(route('quotations.show', $mine))->assertOk();
    $this->getJson('/search?q='.urlencode('عرض مخصص'))->assertJsonCount(1, '0.results');
});

it('blocks deleting a client or lead that has a quotation', function () {
    $lead = Lead::factory()->create(['client_id' => Client::factory()->create()->id]);
    $q = Quotation::factory()->create(['lead_id' => $lead->id, 'client_id' => $lead->client_id]);

    expect($q->client->deletionBlockedReason(scopedUser()))->not->toBeNull()
        ->and($lead->deletionBlockedReason(scopedUser()))->not->toBeNull();
});

it('renders the create form with the default payment template and terms', function () {
    actingAs($this->user)->get(route('quotations.create'))->assertOk()
        ->assertSee(__('quotations.items'))->assertSee('دفعة مقدمة');
});
