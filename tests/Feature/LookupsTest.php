<?php

use App\Enums\LookupType;
use App\Livewire\Tables\LookupsTable;
use App\Models\Lookup;
use App\Support\LookupUsage;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

const LOOKUPS_ALL = ['lookups.view', 'lookups.create', 'lookups.update', 'lookups.delete', 'lookups.restore', 'lookups.force-delete', 'lookups.export'];

it('falls back to Arabic when the English name is empty', function () {
    app()->setLocale('en');

    expect(Lookup::factory()->make(['name_ar' => 'عزل', 'name_en' => null])->name)->toBe('عزل')
        ->and(Lookup::factory()->make(['name_ar' => 'عزل', 'name_en' => 'Insulation'])->name)->toBe('Insulation');

    app()->setLocale('ar');
    expect(Lookup::factory()->make(['name_ar' => 'عزل', 'name_en' => 'Insulation'])->name)->toBe('عزل');
});

it('returns active options in their order', function () {
    Lookup::factory()->create(['type' => LookupType::Unit, 'name_ar' => 'ثان', 'sort_order' => 2]);
    Lookup::factory()->create(['type' => LookupType::Unit, 'name_ar' => 'أول', 'sort_order' => 1]);
    Lookup::factory()->create(['type' => LookupType::Unit, 'name_ar' => 'موقوف', 'sort_order' => 0, 'is_active' => false]);
    Lookup::factory()->create(['type' => LookupType::Service, 'name_ar' => 'خدمة']);

    expect(Lookup::options(LookupType::Unit)->pluck('name_ar')->all())->toBe(['أول', 'ثان']);
});

it('caches options and refreshes them on save', function () {
    $unit = Lookup::factory()->create(['type' => LookupType::Unit, 'name_ar' => 'متر']);
    Lookup::options(LookupType::Unit);

    DB::enableQueryLog();
    Lookup::options(LookupType::Unit);
    expect(DB::getQueryLog())->toBe([]);

    $unit->update(['name_ar' => 'متر مربع']);
    expect(Lookup::options(LookupType::Unit)->pluck('name_ar')->all())->toBe(['متر مربع']);

    $unit->delete();
    expect(Lookup::options(LookupType::Unit))->toBeEmpty();
});

it('narrows child options to their parent', function () {
    $abuDhabi = Lookup::factory()->create(['type' => LookupType::Emirate, 'name_ar' => 'أبوظبي']);
    $dubai = Lookup::factory()->create(['type' => LookupType::Emirate, 'name_ar' => 'دبي']);
    Lookup::factory()->create(['type' => LookupType::Area, 'name_ar' => 'مصفح', 'parent_id' => $abuDhabi->id]);
    Lookup::factory()->create(['type' => LookupType::Area, 'name_ar' => 'ديرة', 'parent_id' => $dubai->id]);

    expect(Lookup::options(LookupType::Area, $abuDhabi->id)->pluck('name_ar')->all())->toBe(['مصفح']);
});

it('returns 403 on the lists screen without lookups.view', function () {
    actingAs(userWith())->get(route('lookups.index', 'service'))->assertForbidden();
});

it('returns 404 for an unknown list type', function () {
    actingAs(userWith(LOOKUPS_ALL))->get('/settings/lists/nonsense')->assertNotFound();
});

it('shows the list screen with its rows', function () {
    Lookup::factory()->create(['type' => LookupType::Service, 'name_ar' => 'أرضيات الإيبوكسي']);

    actingAs(userWith(LOOKUPS_ALL))->get(route('lookups.index', 'service'))->assertOk()->assertSee('أرضيات الإيبوكسي');
});

it('creates a row from the modal', function () {
    actingAs(userWith(LOOKUPS_ALL))->postJson(route('lookups.store', 'unit'), ['name_ar' => 'متر طولي', 'name_en' => 'Linear metre'])
        ->assertOk()->assertJsonPath('message', __('app.saved'));

    $unit = Lookup::first();
    expect($unit->type)->toBe(LookupType::Unit)->and($unit->name_en)->toBe('Linear metre')->and($unit->is_active)->toBeTrue()->and($unit->sort_order)->toBe(1);
});

it('returns 422 JSON with field errors from the modal', function () {
    actingAs(userWith(LOOKUPS_ALL))->postJson(route('lookups.store', 'unit'), ['name_en' => 'x'])
        ->assertStatus(422)->assertJsonValidationErrors('name_ar');
});

it('rejects a duplicate name inside the same list only', function () {
    Lookup::factory()->create(['type' => LookupType::Unit, 'name_ar' => 'عدد']);
    $actor = userWith(LOOKUPS_ALL);

    actingAs($actor)->postJson(route('lookups.store', 'unit'), ['name_ar' => 'عدد'])->assertStatus(422);
    actingAs($actor)->postJson(route('lookups.store', 'worker_trade'), ['name_ar' => 'عدد'])->assertOk();
});

it('requires an emirate parent for an area', function () {
    $emirate = Lookup::factory()->create(['type' => LookupType::Emirate]);
    $unit = Lookup::factory()->create(['type' => LookupType::Unit]);
    $actor = userWith(LOOKUPS_ALL);

    actingAs($actor)->postJson(route('lookups.store', 'area'), ['name_ar' => 'مصفح'])->assertJsonValidationErrors('parent_id');
    actingAs($actor)->postJson(route('lookups.store', 'area'), ['name_ar' => 'مصفح', 'parent_id' => $unit->id])->assertJsonValidationErrors('parent_id');
    actingAs($actor)->postJson(route('lookups.store', 'area'), ['name_ar' => 'مصفح', 'parent_id' => $emirate->id])->assertOk();
});

it('updates a row and keeps its system code', function () {
    $status = Lookup::factory()->create(['type' => LookupType::LeadStatus, 'name_ar' => 'جديد', 'code' => 'new', 'is_system' => true]);

    actingAs(userWith(LOOKUPS_ALL))->putJson(route('lookups.update', ['lead_status', $status]), [
        'name_ar' => 'طلب جديد', 'color' => 'info', 'is_active' => '1', 'code' => 'hacked',
    ])->assertOk();

    expect($status->fresh())->name_ar->toBe('طلب جديد')->color->toBe('info')->code->toBe('new');
});

it('cannot update a row through another list', function () {
    $unit = Lookup::factory()->create(['type' => LookupType::Unit]);

    actingAs(userWith(LOOKUPS_ALL))->putJson(route('lookups.update', ['service', $unit]), ['name_ar' => 'x'])->assertNotFound();
});

it('refuses to delete a system row, a row with children, or a row in use', function () {
    $system = Lookup::factory()->create(['type' => LookupType::LeadSource, 'is_system' => true]);
    $emirate = Lookup::factory()->create(['type' => LookupType::Emirate]);
    Lookup::factory()->create(['type' => LookupType::Area, 'parent_id' => $emirate->id]);
    $used = Lookup::factory()->create(['type' => LookupType::WorkerTrade, 'name_ar' => 'مستخدم']);
    $free = Lookup::factory()->create(['type' => LookupType::WorkerTrade, 'name_ar' => 'حر']);

    LookupUsage::register(LookupType::WorkerTrade, fn (Lookup $lookup) => $lookup->name_ar === 'مستخدم');

    actingAs(userWith(LOOKUPS_ALL));
    Livewire::test(LookupsTable::class, ['type' => 'lead_source'])->call('deleteOne', $system->id);
    Livewire::test(LookupsTable::class, ['type' => 'emirate'])->call('deleteOne', $emirate->id);
    Livewire::test(LookupsTable::class, ['type' => 'worker_trade'])->call('deleteAll');

    expect($system->fresh()->trashed())->toBeFalse()
        ->and($emirate->fresh()->trashed())->toBeFalse()
        ->and($used->fresh()->trashed())->toBeFalse()
        ->and($free->fresh()->trashed())->toBeTrue();
});

it('lists only its own type', function () {
    Lookup::factory()->create(['type' => LookupType::Unit, 'name_ar' => 'وحدة']);
    Lookup::factory()->create(['type' => LookupType::Service, 'name_ar' => 'خدمة']);

    actingAs(userWith(LOOKUPS_ALL));
    $rows = Livewire::test(LookupsTable::class, ['type' => 'unit'])->viewData('rows');

    expect(collect($rows->items())->pluck('name_ar')->all())->toBe(['وحدة']);
});

it('persists a new order for its own list only', function () {
    [$a, $b, $c] = Lookup::factory()->count(3)->create(['type' => LookupType::Unit])->all();
    $other = Lookup::factory()->create(['type' => LookupType::Service, 'sort_order' => 9]);

    actingAs(userWith(LOOKUPS_ALL))->postJson(route('lookups.reorder', 'unit'), ['ids' => [$c->id, $a->id, $other->id, $b->id]])->assertOk();

    expect([$c->fresh()->sort_order, $a->fresh()->sort_order, $b->fresh()->sort_order])->toBe([1, 2, 3])
        ->and($other->fresh()->sort_order)->toBe(9);
});

it('forbids writing without the matching permission', function () {
    $unit = Lookup::factory()->create(['type' => LookupType::Unit]);
    $viewer = userWith(['lookups.view']);

    actingAs($viewer)->postJson(route('lookups.store', 'unit'), ['name_ar' => 'x'])->assertForbidden();
    actingAs($viewer)->putJson(route('lookups.update', ['unit', $unit]), ['name_ar' => 'x'])->assertForbidden();
    actingAs($viewer)->postJson(route('lookups.reorder', 'unit'), ['ids' => [$unit->id]])->assertForbidden();
});

it('seeds the starting lists in both languages', function () {
    $this->seed(LookupSeeder::class);
    $this->seed(LookupSeeder::class);

    expect(Lookup::ofType(LookupType::LeadStatus)->orderBy('sort_order')->pluck('code')->all())->toBe(['new', 'contacted', 'visit', 'quoted', 'won', 'lost'])
        ->and(Lookup::ofType(LookupType::Service)->count())->toBe(9)
        ->and(Lookup::ofType(LookupType::Emirate)->count())->toBe(7)
        ->and(Lookup::ofType(LookupType::LeadSource)->where('code', 'website')->value('is_system'))->toBeTrue()
        ->and(Lookup::whereNull('name_en')->count())->toBe(0);
});
