<?php

use App\Actions\ImportPriceItems;
use App\Enums\LookupType;
use App\Models\Lookup;
use App\Models\PriceItem;
use Database\Seeders\LookupSeeder;
use Illuminate\Http\UploadedFile;
use Spatie\SimpleExcel\SimpleExcelWriter;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(LookupSeeder::class);
    $this->unit = Lookup::query()->ofType(LookupType::Unit)->where('name_ar', 'م²')->firstOrFail();
    $this->service = Lookup::query()->ofType(LookupType::Service)->firstOrFail();
});

function priceSheet(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'pi').'.xlsx';
    $writer = SimpleExcelWriter::create($path);
    foreach ($rows as $row) {
        $writer->addRow($row);
    }
    $writer->close();

    return $path;
}

it('creates and edits a price item from the modal as JSON', function () {
    $user = scopedUser(['price_items.view', 'price_items.create', 'price_items.update']);

    actingAs($user)->postJson(route('price-items.store'), [
        'description_ar' => 'عزل مائي بالبيتومين طبقتين',
        'unit_id' => $this->unit->id,
        'service_id' => $this->service->id,
        'default_price' => '45.50',
        'is_active' => '1',
    ])->assertCreated();

    $item = PriceItem::query()->firstOrFail();
    actingAs($user)->putJson(route('price-items.update', $item), ['description_ar' => $item->description_ar, 'unit_id' => $this->unit->id, 'default_price' => '48'])->assertOk();

    expect($item->fresh()->default_price)->toBe('48.00');
    actingAs($user)->postJson(route('price-items.store'), ['unit_id' => $this->unit->id])->assertUnprocessable()->assertJsonValidationErrors(['description_ar', 'default_price']);
});

it('imports rows, updates an existing item price and reports bad rows', function () {
    PriceItem::factory()->create(['description_ar' => 'بلاط سيراميك', 'unit_id' => $this->unit->id, 'default_price' => '60']);

    $result = app(ImportPriceItems::class)->handle(priceSheet([
        ['description_ar' => 'دهان داخلي', 'description_en' => 'Interior paint', 'service' => $this->service->name_en, 'unit' => 'm²', 'price' => '25', 'active' => '1'],
        ['description_ar' => 'بلاط سيراميك', 'description_en' => '', 'service' => '', 'unit' => 'م²', 'price' => '65', 'active' => '1'],
        ['description_ar' => 'بند مجهول', 'description_en' => '', 'service' => '', 'unit' => 'وحدة غير موجودة', 'price' => '10', 'active' => '1'],
    ]), scopedUser());

    expect($result->created)->toBe(1)
        ->and($result->updated)->toBe(1)
        ->and($result->errors)->toHaveCount(1)
        ->and($result->errors[0])->toContain('4')
        ->and(PriceItem::query()->where('description_ar', 'بلاط سيراميك')->value('default_price'))->toBe('65.00')
        ->and(PriceItem::query()->where('description_ar', 'دهان داخلي')->value('service_id'))->toBe($this->service->id);
});

it('imports nothing when most rows fail', function () {
    $result = app(ImportPriceItems::class)->handle(priceSheet([
        ['description_ar' => 'صالح', 'unit' => 'م²', 'price' => '10'],
        ['description_ar' => '', 'unit' => 'م²', 'price' => '10'],
        ['description_ar' => 'سعر سالب', 'unit' => 'م²', 'price' => '-5'],
    ]), scopedUser());

    expect($result->created)->toBe(0)->and(PriceItem::query()->count())->toBe(0)->and($result->errors)->toHaveCount(2);
});

it('rejects an import file that is not xlsx or csv', function () {
    actingAs(scopedUser(['price_items.view', 'price_items.create']))
        ->post(route('price-items.import'), ['file' => UploadedFile::fake()->create('items.pdf', 10, 'application/pdf')])
        ->assertSessionHasErrors('file');
});

it('imports from the upload screen and reports the counts', function () {
    $path = priceSheet([['description_ar' => 'خرسانة جاهزة', 'unit' => 'م²', 'price' => '300']]);

    actingAs(scopedUser(['price_items.view', 'price_items.create']))
        ->post(route('price-items.import'), ['file' => new UploadedFile($path, 'items.xlsx', null, null, true)])
        ->assertRedirect(route('price-items.index'))->assertSessionHas('success');

    expect(PriceItem::query()->count())->toBe(1);
});

it('finds active items for the quotation picker by text and service', function () {
    PriceItem::factory()->create(['description_ar' => 'عزل حراري للأسطح', 'service_id' => $this->service->id, 'unit_id' => $this->unit->id]);
    PriceItem::factory()->create(['description_ar' => 'عزل صوتي', 'is_active' => false, 'unit_id' => $this->unit->id]);

    actingAs(scopedUser(['quotations.create']))->getJson(route('price-items.search', ['q' => 'عزل']))
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.unit_id', $this->unit->id)->assertJsonPath('0.price', '0.00');
});

it('refuses to delete a unit used by a price item', function () {
    PriceItem::factory()->create(['unit_id' => $this->unit->id]);

    expect($this->unit->deletionBlockedReason(scopedUser()))->not->toBeNull();
});
