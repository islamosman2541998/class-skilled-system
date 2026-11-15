<?php

use App\Actions\Projects\SaveProject;
use App\Actions\Quotations\ChangeQuotationStatus;
use App\Actions\Quotations\SaveQuotation;
use App\Enums\LookupType;
use App\Models\Client;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\ProjectItem;
use App\Models\Variation;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['projects.view', 'projects.create', 'projects.update', 'projects.approve', 'quotations.view', 'quotations.approve', 'claims.view']);
});

function claimsProject(array $attributes = []): Project
{
    return Project::factory()->create([
        'manager_id' => auth()->id() ?? test()->user->id,
        'billing_method' => 'claims',
        'original_value' => '100000',
        'contract_value' => '100000',
        'vat_rate' => '5.00',
        ...$attributes,
    ]);
}

it('converting a quotation with a discount into a claims project copies items, skips payments and stores the discount rate', function () {
    $quotation = app(SaveQuotation::class)->handle([
        'client_id' => Client::factory()->create()->id,
        'service_id' => Lookup::query()->ofType(LookupType::Service)->value('id'),
        'title_ar' => 'مبنى تجاري',
        'issue_date' => '2026-11-16',
        'valid_until' => '2026-12-16',
        'assigned_to' => $this->user->id,
        'discount_type' => 'amount',
        'discount_value' => '1000',
        'items' => [
            ['section_ar' => 'الهيكل', 'description_ar' => 'خرسانة', 'quantity' => '100', 'unit_price' => '150'],
            ['description_ar' => 'حماية', 'quantity' => '1', 'unit_price' => '5000'],
        ],
        'payments' => [['label_ar' => 'مقدم', 'percent' => '30'], ['label_ar' => 'تسليم', 'percent' => '70']],
    ], $this->user);
    app(ChangeQuotationStatus::class)->accept($quotation, $this->user);

    actingAs($this->user)->post(route('quotations.convert', $quotation), [
        'start_date' => '2026-11-20', 'planned_end_date' => '2027-02-20', 'manager_id' => $this->user->id, 'billing_method' => 'claims',
    ])->assertRedirect();

    $project = Project::query()->firstOrFail();

    expect($quotation->fresh()->subtotal)->toBe('20000.00')
        ->and($project->items)->toHaveCount(2)
        ->and($project->installments()->count())->toBe(0)
        ->and($project->contract_discount_percent)->toBe('5.0000')
        ->and($project->retention_percent)->toBe('5.00')
        ->and($project->advance_amount)->toBe('0.00');
});

it('approved variation on a claims project adds no installment', function () {
    $project = claimsProject();

    actingAs($this->user)->post(route('projects.variations.store', $project), [
        'description' => 'إضافة مظلة',
        'items' => [['description_ar' => 'مظلة سيارات', 'quantity' => '1', 'unit_price' => '10000']],
    ])->assertRedirect();
    actingAs($this->user)->patch(route('projects.variations.approve', [$project, Variation::query()->firstOrFail()]))->assertRedirect();

    expect($project->fresh()->contract_value)->toBe('110500.00')
        ->and($project->installments()->count())->toBe(0);
});

it('claims project shows the claims tab and hides payments; installments project the opposite', function () {
    $claims = claimsProject();
    $installments = claimsProject(['billing_method' => 'installments']);

    $tabs = array_keys(actingAs($this->user)->get(route('projects.show', $claims))->assertOk()->viewData('tabs'));
    expect($tabs)->toContain('claims')->not->toContain('payments');

    $tabs = array_keys(actingAs($this->user)->get(route('projects.show', $installments))->assertOk()->viewData('tabs'));
    expect($tabs)->toContain('payments')->not->toContain('claims');

    actingAs($this->user)->get(route('projects.show', [$claims, 'tab' => 'claims']))->assertOk()
        ->assertSee(__('site.setup.title'))
        ->assertSee(__('site.items.title'));
});

it('adds, edits and deletes contract items on a direct claims project', function () {
    $project = app(SaveProject::class)->handle([
        'client_id' => Client::factory()->create()->id,
        'title_ar' => 'مستودع',
        'original_value' => '50000',
        'billing_method' => 'claims',
        'start_date' => '2026-11-20',
        'manager_id' => $this->user->id,
    ], $this->user);
    $unit = Lookup::query()->ofType(LookupType::Unit)->value('id');

    expect($project->fresh()->retention_percent)->toBe('5.00')
        ->and($project->fresh()->contract_discount_percent)->toBe('0.0000');

    actingAs($this->user)->post(route('projects.items.store', $project), [
        'section_ar' => 'الأرضيات', 'description_ar' => 'بلاط', 'description_en' => 'Tiles', 'unit_id' => $unit, 'quantity' => '10', 'unit_price' => '25.50',
    ])->assertRedirect(route('projects.show', [$project, 'tab' => 'claims']));

    $item = ProjectItem::query()->firstOrFail();
    expect($item->line_total)->toBe('255.00')->and($item->sort_order)->toBe(1);

    actingAs($this->user)->put(route('projects.items.update', [$project, $item]), [
        'description_ar' => 'بلاط', 'quantity' => '20', 'unit_price' => '25.50',
    ])->assertRedirect();
    expect($item->fresh()->line_total)->toBe('510.00')->and($item->fresh()->section_ar)->toBeNull();

    actingAs($this->user)->delete(route('projects.items.destroy', [$project, $item]))->assertRedirect();
    $this->assertSoftDeleted($item);

    actingAs($this->user)->post(route('projects.items.store', $project), ['description_ar' => 'بلاط', 'quantity' => '0', 'unit_price' => '-1'])
        ->assertSessionHasErrors(['quantity', 'unit_price']);

    actingAs(scopedUser(['projects.view', 'claims.view']))->post(route('projects.items.store', $project), ['description_ar' => 'بلاط', 'quantity' => '1', 'unit_price' => '1'])
        ->assertForbidden();
    actingAs($this->user)->post(route('projects.items.store', claimsProject(['billing_method' => 'installments'])), ['description_ar' => 'بلاط', 'quantity' => '1', 'unit_price' => '1'])
        ->assertNotFound();
});

it('saves retention, discount and advance', function () {
    $project = claimsProject();

    actingAs($this->user)->put(route('projects.claim-setup.update', $project), [
        'retention_percent' => '10', 'contract_discount_percent' => '2.5', 'advance_amount' => '5000',
    ])->assertRedirect(route('projects.show', [$project, 'tab' => 'claims']));

    $project->refresh();
    expect($project->retention_percent)->toBe('10.00')
        ->and($project->contract_discount_percent)->toBe('2.5000')
        ->and($project->advance_amount)->toBe('5000.00');

    actingAs($this->user)->put(route('projects.claim-setup.update', $project), [
        'retention_percent' => '101', 'contract_discount_percent' => '-1', 'advance_amount' => '-5',
    ])->assertSessionHasErrors(['retention_percent', 'contract_discount_percent', 'advance_amount']);
});

it('keeps the billing method chosen at creation', function () {
    $project = claimsProject();

    actingAs($this->user)->put(route('projects.update', $project), [
        'title_ar' => $project->title_ar, 'original_value' => '100000', 'billing_method' => 'installments',
        'start_date' => '2026-11-20', 'manager_id' => $this->user->id, 'status' => 'in_progress',
    ])->assertSessionHasNoErrors()->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->billing_method->value)->toBe('claims');
});
