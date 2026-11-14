<?php

use App\Enums\LookupType;
use App\Models\Lookup;
use App\Models\StageTemplate;
use Database\Seeders\LookupSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed(LookupSeeder::class);
    $this->service = Lookup::query()->ofType(LookupType::Service)->firstOrFail();
    $this->other = Lookup::query()->ofType(LookupType::Service)->skip(1)->firstOrFail();
});

function templateRows(): array
{
    return [
        ['name_ar' => 'التجهيز والحفر', 'name_en' => 'Preparation', 'weight' => '10'],
        ['name_ar' => 'الهيكل الخرساني', 'name_en' => 'Structure', 'weight' => '50'],
        ['name_ar' => 'التشطيبات', 'name_en' => 'Finishing', 'weight' => '40'],
    ];
}

it('saves the stage template of a service in order', function () {
    actingAs(scopedUser(['settings.manage']))
        ->put(route('settings.stage-templates.update', ['service' => $this->service->id]), ['stages' => templateRows()])
        ->assertRedirect()->assertSessionHasNoErrors();

    $stages = StageTemplate::forService($this->service->id);

    expect($stages->pluck('name_ar')->all())->toBe(['التجهيز والحفر', 'الهيكل الخرساني', 'التشطيبات'])
        ->and($stages->pluck('weight')->all())->toBe(['10.00', '50.00', '40.00']);
});

it('rejects a stage with weight 0 or no Arabic name', function () {
    $user = scopedUser(['settings.manage']);

    actingAs($user)->put(route('settings.stage-templates.update', ['service' => $this->service->id]), ['stages' => [['name_ar' => 'x', 'weight' => '0']]])
        ->assertSessionHasErrors('stages.0.weight');
    actingAs($user)->put(route('settings.stage-templates.update', ['service' => $this->service->id]), ['stages' => [['name_ar' => '', 'weight' => '10']]])
        ->assertSessionHasErrors('stages.0.name_ar');
});

it('keeps other services untouched when one is saved', function () {
    StageTemplate::query()->create(['service_id' => $this->other->id, 'name_ar' => 'مرحلة أخرى', 'weight' => '100', 'sort_order' => 1]);

    actingAs(scopedUser(['settings.manage']))
        ->put(route('settings.stage-templates.update', ['service' => $this->service->id]), ['stages' => templateRows()]);

    expect(StageTemplate::forService($this->other->id))->toHaveCount(1);
});

it('returns 403 without settings.manage', function () {
    actingAs(scopedUser())->get(route('settings.stage-templates'))->assertForbidden();
});

it('renders the screen for a chosen service', function () {
    StageTemplate::query()->create(['service_id' => $this->service->id, 'name_ar' => 'مرحلة الأساسات', 'weight' => '100', 'sort_order' => 1]);

    actingAs(scopedUser(['settings.manage']))->get(route('settings.stage-templates', ['service' => $this->service->id]))
        ->assertOk()->assertSee('مرحلة الأساسات');
});

it('refuses to delete a service used by a template', function () {
    StageTemplate::query()->create(['service_id' => $this->service->id, 'name_ar' => 'مرحلة', 'weight' => '100', 'sort_order' => 1]);

    expect($this->service->deletionBlockedReason(scopedUser()))->not->toBeNull();
});
