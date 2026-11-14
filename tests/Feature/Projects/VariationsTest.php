<?php

use App\Enums\VariationStatus;
use App\Models\Project;
use App\Models\Variation;
use App\Services\InstallmentSchedule;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-15 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['projects.view', 'projects.update', 'projects.approve']);
    $this->project = Project::factory()->create(['manager_id' => $this->user->id, 'original_value' => '100000', 'contract_value' => '100000', 'vat_rate' => '5.00']);
});

function variationPayload(?array $items = null): array
{
    return [
        'description' => 'إضافة مظلة سيارات وإلغاء جزء من العزل',
        'items' => $items ?? [
            ['description_ar' => 'مظلة سيارات', 'quantity' => '1', 'unit_price' => '12000'],
            ['description_ar' => 'إلغاء عزل الحديقة', 'quantity' => '-50', 'unit_price' => '40'],
        ],
    ];
}

it('creates a variation with number VO-2026-0001 and signed totals', function () {
    actingAs($this->user)->post(route('projects.variations.store', $this->project), variationPayload())->assertRedirect();

    $variation = Variation::query()->with('items')->firstOrFail();

    expect($variation->number)->toBe('VO-2026-0001')
        ->and($variation->status)->toBe(VariationStatus::Proposed)
        ->and($variation->items[1]->line_total)->toBe('-2000.00')
        ->and($variation->subtotal)->toBe('10000.00')
        ->and($variation->vat_amount)->toBe('500.00')
        ->and($variation->total)->toBe('10500.00')
        ->and($this->project->fresh()->contract_value)->toBe('100000.00');
});

it('approving adds to the contract value once and adds an installment', function () {
    actingAs($this->user)->post(route('projects.variations.store', $this->project), variationPayload());
    $variation = Variation::query()->firstOrFail();

    actingAs($this->user)->patch(route('projects.variations.approve', [$this->project, $variation]))->assertRedirect();
    actingAs($this->user)->patch(route('projects.variations.approve', [$this->project, $variation]))->assertSessionHas('error');

    $project = $this->project->fresh();
    expect($project->variations_total)->toBe('10500.00')
        ->and($project->contract_value)->toBe('110500.00')
        ->and($project->installments()->where('variation_id', $variation->id)->count())->toBe(1)
        ->and($project->installments()->where('variation_id', $variation->id)->value('amount'))->toBe('10500.00')
        ->and($variation->fresh()->status)->toBe(VariationStatus::Approved);
});

it('approving after rejection changes nothing', function () {
    actingAs($this->user)->post(route('projects.variations.store', $this->project), variationPayload());
    $variation = Variation::query()->firstOrFail();

    actingAs($this->user)->patch(route('projects.variations.reject', [$this->project, $variation]), ['rejection_note' => 'العميل رفض'])->assertRedirect();
    actingAs($this->user)->patch(route('projects.variations.approve', [$this->project, $variation]))->assertSessionHas('error');

    expect($this->project->fresh()->contract_value)->toBe('100000.00')->and($variation->fresh()->status)->toBe(VariationStatus::Rejected);
});

it('a removal variation lowers the contract value', function () {
    actingAs($this->user)->post(route('projects.variations.store', $this->project), variationPayload([['description_ar' => 'إلغاء دهان خارجي', 'quantity' => '-100', 'unit_price' => '50']]));
    $variation = Variation::query()->firstOrFail();
    actingAs($this->user)->patch(route('projects.variations.approve', [$this->project, $variation]));

    expect($variation->fresh()->total)->toBe('-5250.00')->and($this->project->fresh()->contract_value)->toBe('94750.00');
});

it('the installment warning uses the value after variations', function () {
    InstallmentSchedule::fromPercents($this->project, [['label_ar' => 'كامل', 'percent' => '100']]);
    actingAs($this->user)->post(route('projects.variations.store', $this->project), variationPayload());
    actingAs($this->user)->patch(route('projects.variations.approve', [$this->project, Variation::query()->firstOrFail()]));

    expect(InstallmentSchedule::difference($this->project->fresh()))->toBe('0.00');
});

it('needs projects.approve to decide', function () {
    actingAs($this->user)->post(route('projects.variations.store', $this->project), variationPayload());
    $variation = Variation::query()->firstOrFail();
    $member = scopedUser(['projects.view', 'projects.update']);
    $this->project->members()->attach($member->id);

    actingAs($member)->patch(route('projects.variations.approve', [$this->project, $variation]))->assertForbidden();
    expect($variation->fresh()->status)->toBe(VariationStatus::Proposed);
});

it('refuses to edit a decided variation', function () {
    actingAs($this->user)->post(route('projects.variations.store', $this->project), variationPayload());
    $variation = Variation::query()->firstOrFail();
    actingAs($this->user)->patch(route('projects.variations.approve', [$this->project, $variation]));

    actingAs($this->user)->put(route('projects.variations.update', [$this->project, $variation]), variationPayload())->assertSessionHas('error');
});

it('lists variations on the project tab and shows one', function () {
    actingAs($this->user)->post(route('projects.variations.store', $this->project), variationPayload());
    $variation = Variation::query()->firstOrFail();

    actingAs($this->user)->get(route('projects.show', [$this->project, 'tab' => 'variations']))->assertOk()->assertSee('VO-2026-0001');
    actingAs($this->user)->get(route('projects.variations.show', [$this->project, $variation]))->assertOk()->assertSee('مظلة سيارات');
});
