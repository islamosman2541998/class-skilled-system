<?php

use App\Actions\Projects\DecideVariation;
use App\Actions\Projects\SaveVariation;
use App\Actions\Site\SaveClaim;
use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\Project;
use App\Models\Variation;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['projects.view', 'projects.update', 'projects.approve', 'claims.view', 'claims.create', 'claims.update', 'claims.delete', 'claims.approve']);
    $this->project = Project::factory()->create([
        'manager_id' => $this->user->id, 'billing_method' => 'claims', 'status' => 'in_progress', 'start_date' => '2026-11-01',
        'original_value' => '26250', 'contract_value' => '26250', 'vat_rate' => '5.00', 'retention_percent' => '10.00',
    ]);
    $this->area = $this->project->items()->create(['description_ar' => 'بلاط', 'quantity' => '100', 'unit_price' => '50', 'line_total' => '5000', 'sort_order' => 1]);
    $this->lump = $this->project->items()->create(['description_ar' => 'تكييف', 'quantity' => '1', 'unit_price' => '20000', 'line_total' => '20000', 'sort_order' => 2]);
});

/** Starts a claim through the screen and returns it. */
function startClaim(Project $project): Claim
{
    actingAs(test()->user)->post(route('claims.store', $project))->assertRedirect();

    return Claim::query()->latest('id')->firstOrFail();
}

/** Enters some work on the first line so the claim can be submitted. */
function workOn(Claim $claim): void
{
    $line = $claim->lines()->orderBy('sort_order')->firstOrFail();
    app(SaveClaim::class)->handle($claim, [
        'period_from' => $claim->period_from->toDateString(), 'period_to' => $claim->period_to->toDateString(),
        'lines' => [$line->id => ['current_quantity' => '1']],
    ], test()->user);
}

/** Line id of a claim for a project item. */
function lineFor(Claim $claim, int $itemId): int
{
    return (int) $claim->lines()->where('project_item_id', $itemId)->value('id');
}

it('starts the first claim with zero previous', function () {
    $claim = startClaim($this->project);

    expect($claim->status)->toBe(ClaimStatus::Draft)
        ->and($claim->number)->toBeNull()
        ->and($claim->client_id)->toBe($this->project->client_id)
        ->and($claim->period_from->toDateString())->toBe('2026-11-01')
        ->and($claim->period_to->toDateString())->toBe('2026-11-16')
        ->and($claim->vat_rate)->toBe('5.00')
        ->and($claim->retention_percent)->toBe('10.00')
        ->and($claim->lines)->toHaveCount(2)
        ->and($claim->lines->pluck('previous_quantity')->all())->toBe(['0.000', '0.000'])
        ->and($claim->lines[0]->contract_quantity)->toBe('100.000');
});

it('second claim takes previous from the approved first', function () {
    $first = startClaim($this->project);
    actingAs($this->user)->put(route('claims.update', $first), [
        'period_from' => '2026-11-01', 'period_to' => '2026-11-10',
        'lines' => [lineFor($first, $this->area->id) => ['current_quantity' => '40']],
    ])->assertSessionHasNoErrors();
    $first->forceFill(['status' => ClaimStatus::Approved])->save();

    $second = startClaim($this->project);

    expect($second->id)->not->toBe($first->id)
        ->and($second->period_from->toDateString())->toBe('2026-11-11')
        ->and($second->lines()->where('project_item_id', $this->area->id)->value('previous_quantity'))->toBe('40.000')
        ->and($second->lines()->where('project_item_id', $this->lump->id)->value('previous_quantity'))->toBe('0.000');
});

it('refuses a second open claim', function () {
    startClaim($this->project);

    actingAs($this->user)->post(route('claims.store', $this->project))->assertSessionHas('error', __('site.claims.open_exists'));

    expect(Claim::query()->count())->toBe(1);
});

it('refuses a cumulative above the contract and a negative cumulative', function () {
    $claim = startClaim($this->project);
    $line = lineFor($claim, $this->area->id);

    actingAs($this->user)->put(route('claims.update', $claim), ['period_from' => '2026-11-01', 'period_to' => '2026-11-16', 'lines' => [$line => ['current_quantity' => '101']]])
        ->assertSessionHasErrors("lines.$line");
    actingAs($this->user)->put(route('claims.update', $claim), ['period_from' => '2026-11-01', 'period_to' => '2026-11-16', 'lines' => [$line => ['current_quantity' => '-5']]])
        ->assertSessionHasErrors("lines.$line");
    actingAs($this->user)->put(route('claims.update', $claim), ['period_from' => '2026-11-01', 'period_to' => '2026-11-16', 'lines' => [$line => ['cumulative_percent' => '120']]])
        ->assertSessionHasErrors("lines.$line");

    expect($claim->lines()->whereKey($line)->value('current_quantity'))->toBe('0.000');
});

it('percent entry stores the quantity', function () {
    $claim = startClaim($this->project);

    actingAs($this->user)->put(route('claims.update', $claim), [
        'period_from' => '2026-11-01', 'period_to' => '2026-11-16',
        'lines' => [
            lineFor($claim, $this->area->id) => ['current_quantity' => '30'],
            lineFor($claim, $this->lump->id) => ['cumulative_percent' => '50'],
        ],
    ])->assertSessionHasNoErrors()->assertRedirect(route('claims.show', $claim));

    $claim->refresh();
    expect($claim->lines()->where('project_item_id', $this->lump->id)->value('current_quantity'))->toBe('0.500')
        ->and($claim->work_current)->toBe('11500.00')
        ->and($claim->gross_amount)->toBe('11500.00')
        ->and($claim->retention_amount)->toBe('1150.00')
        ->and($claim->net_amount)->toBe('10350.00')
        ->and($claim->vat_amount)->toBe('517.50')
        ->and($claim->total)->toBe('10867.50');
});

it('submitting reserves PC-2026-0001 and freezes the lines', function () {
    $claim = startClaim($this->project);
    $line = lineFor($claim, $this->area->id);
    actingAs($this->user)->put(route('claims.update', $claim), ['period_from' => '2026-11-01', 'period_to' => '2026-11-16', 'lines' => [$line => ['current_quantity' => '30']]]);

    actingAs($this->user)->patch(route('claims.submit', $claim))->assertRedirect(route('claims.show', $claim));
    actingAs($this->user)->patch(route('claims.submit', $claim))->assertRedirect();

    $claim->refresh();
    expect($claim->number)->toBe('PC-2026-0001')
        ->and($claim->status)->toBe(ClaimStatus::Submitted)
        ->and($claim->submitted_by)->toBe($this->user->id);

    actingAs($this->user)->put(route('claims.update', $claim), ['period_from' => '2026-11-01', 'period_to' => '2026-11-16', 'lines' => [$line => ['current_quantity' => '50']]])
        ->assertSessionHas('error');
    expect($claim->lines()->whereKey($line)->value('current_quantity'))->toBe('30.000');
});

it('returning to draft keeps the number and records the note', function () {
    $claim = startClaim($this->project);
    workOn($claim);
    actingAs($this->user)->patch(route('claims.submit', $claim));

    actingAs($this->user)->patch(route('claims.return', $claim), ['return_note' => 'راجع كمية البلاط'])->assertRedirect(route('claims.show', $claim));

    $claim->refresh();
    expect($claim->status)->toBe(ClaimStatus::Draft)
        ->and($claim->number)->toBe('PC-2026-0001')
        ->and($claim->return_note)->toBe('راجع كمية البلاط');

    workOn($claim);
    actingAs($this->user)->patch(route('claims.submit', $claim));
    expect($claim->fresh()->number)->toBe('PC-2026-0001');
});

it('includes items of approved variations only', function () {
    $approved = app(SaveVariation::class)->handle($this->project, ['description' => 'مظلة', 'items' => [['description_ar' => 'مظلة سيارات', 'quantity' => '1', 'unit_price' => '8000']]], $this->user);
    app(DecideVariation::class)->approve($approved, $this->user);
    app(SaveVariation::class)->handle($this->project, ['description' => 'سور', 'items' => [['description_ar' => 'سور خارجي', 'quantity' => '1', 'unit_price' => '3000']]], $this->user);

    $claim = startClaim($this->project);

    expect($claim->lines)->toHaveCount(3)
        ->and($claim->lines->pluck('description_ar')->all())->toBe(['بلاط', 'تكييف', 'مظلة سيارات'])
        ->and($claim->lines[2]->variation_item_id)->toBe($approved->items()->value('id'))
        ->and(Variation::query()->count())->toBe(2);
});

it('engineer prepares claims of own projects, approve needs claims.approve', function () {
    $engineer = scopedUser(['projects.view', 'claims.view', 'claims.create', 'claims.update'], 'assigned');
    $own = Project::factory()->create(['manager_id' => $engineer->id, 'billing_method' => 'claims', 'start_date' => '2026-11-01']);
    $own->items()->create(['description_ar' => 'بند', 'quantity' => '10', 'unit_price' => '100', 'line_total' => '1000', 'sort_order' => 1]);

    actingAs($engineer)->post(route('claims.store', $own))->assertRedirect();
    $claim = Claim::query()->where('project_id', $own->id)->firstOrFail();
    actingAs($engineer)->get(route('claims.edit', $claim))->assertOk();
    workOn($claim);
    actingAs($engineer)->patch(route('claims.submit', $claim))->assertRedirect();
    actingAs($engineer)->patch(route('claims.return', $claim), ['return_note' => 'ملاحظة'])->assertForbidden();

    $foreign = startClaim($this->project);
    actingAs($engineer)->post(route('claims.store', $this->project))->assertNotFound();
    actingAs($engineer)->get(route('claims.show', $foreign))->assertNotFound();
});

it('cannot delete a submitted claim', function () {
    $claim = startClaim($this->project);
    workOn($claim);
    actingAs($this->user)->patch(route('claims.submit', $claim));

    actingAs($this->user)->delete(route('claims.destroy', $claim))->assertSessionHas('error');
    expect(Claim::query()->whereKey($claim->id)->exists())->toBeTrue();

    $claim->refresh()->forceFill(['status' => ClaimStatus::Draft])->save();
    actingAs($this->user)->delete(route('claims.destroy', $claim))->assertRedirect(route('projects.show', [$this->project, 'tab' => 'claims']))->assertSessionHas('success');
    $this->assertSoftDeleted($claim);
});

it('claims setup and contract items are locked once a claim was submitted', function () {
    $claim = startClaim($this->project);

    expect($this->project->fresh()->hasLockedClaims())->toBeFalse();
    workOn($claim);
    actingAs($this->user)->patch(route('claims.submit', $claim));
    expect($this->project->fresh()->hasLockedClaims())->toBeTrue();

    actingAs($this->user)->put(route('projects.claim-setup.update', $this->project), ['retention_percent' => '5', 'contract_discount_percent' => '0', 'advance_amount' => '0'])
        ->assertForbidden();
    actingAs($this->user)->post(route('projects.items.store', $this->project), ['description_ar' => 'بند', 'quantity' => '1', 'unit_price' => '1'])
        ->assertForbidden();
    actingAs($this->user)->delete(route('projects.items.destroy', [$this->project, $this->area]))->assertForbidden();
    actingAs($this->user)->get(route('projects.show', [$this->project, 'tab' => 'claims']))->assertOk()
        ->assertSee(__('site.setup.locked'))
        ->assertSee('PC-2026-0001');
});

it('refuses to submit a claim without work', function () {
    $claim = startClaim($this->project);

    actingAs($this->user)->patch(route('claims.submit', $claim))->assertSessionHas('error', __('site.claims.no_work'));

    expect($claim->fresh()->status)->toBe(ClaimStatus::Draft)->and($claim->fresh()->number)->toBeNull();
});
