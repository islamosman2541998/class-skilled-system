<?php

use App\Actions\Quotations\SaveQuotation;
use App\Enums\DocumentType;
use App\Enums\LookupType;
use App\Enums\QuotationStatus;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\Quotation;
use App\Providers\SalesServiceProvider;
use App\Services\DueTasks;
use App\Services\NumberGenerator;
use App\Settings\FinanceSettings;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-14 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['quotations.view', 'quotations.create', 'quotations.update', 'quotations.approve']);
    $this->quotation = app(SaveQuotation::class)->handle([
        'client_id' => Client::factory()->create()->id,
        'title_ar' => 'تشطيب داخلي',
        'issue_date' => '2026-11-14',
        'valid_until' => '2026-12-14',
        'assigned_to' => $this->user->id,
        'discount_type' => null,
        'discount_value' => '0',
        'items' => [['description_ar' => 'دهان', 'quantity' => '100', 'unit_price' => '20']],
        'payments' => [['label_ar' => 'مقدم', 'percent' => '50'], ['label_ar' => 'تسليم', 'percent' => '50']],
    ], $this->user);
});

it('marks a draft as sent', function () {
    actingAs($this->user)->patch(route('quotations.send', $this->quotation))->assertRedirect();

    $q = $this->quotation->fresh();
    expect($q->status)->toBe(QuotationStatus::Sent)->and($q->sent_at)->not->toBeNull();
});

it('creates revision R1 then R2 and makes the previous one read-only', function () {
    actingAs($this->user)->post(route('quotations.revise', $this->quotation))->assertRedirect();
    $r1 = Quotation::query()->where('revision', 1)->firstOrFail();

    actingAs($this->user)->post(route('quotations.revise', $r1))->assertRedirect();
    $r2 = Quotation::query()->where('revision', 2)->firstOrFail();

    expect($r1->number)->toBe('QT-2026-0001-R1')
        ->and($r2->number)->toBe('QT-2026-0001-R2')
        ->and($r2->parent_id)->toBe($this->quotation->id)
        ->and($r2->items)->toHaveCount(1)
        ->and($r2->total)->toBe($this->quotation->total)
        ->and($this->quotation->fresh()->superseded_at)->not->toBeNull()
        ->and($r1->fresh()->superseded_at)->not->toBeNull();

    actingAs($this->user)->get(route('quotations.edit', $r1))->assertRedirect(route('quotations.show', $r1))->assertSessionHas('error', __('quotations.locked'));
});

it('does not consume a document number for a revision', function () {
    $before = app(NumberGenerator::class)->preview(DocumentType::Quotation);

    actingAs($this->user)->post(route('quotations.revise', $this->quotation));

    expect(app(NumberGenerator::class)->preview(DocumentType::Quotation))->toBe($before);
});

it('copies a quotation with a new number and the current VAT', function () {
    $finance = app(FinanceSettings::class);
    $finance->vat_rate = '10.00';
    $finance->save();

    actingAs($this->user)->post(route('quotations.copy', $this->quotation))->assertRedirect();
    $copy = Quotation::query()->latest('id')->firstOrFail();

    expect($copy->number)->toBe('QT-2026-0002')
        ->and($copy->status)->toBe(QuotationStatus::Draft)
        ->and($copy->vat_rate)->toBe('10.00')
        ->and($copy->total)->toBe('2200.00')
        ->and($copy->parent_id)->toBeNull();
});

it('accepts only with quotations.approve and moves the lead to won', function () {
    $lead = Lead::factory()->create(['client_id' => $this->quotation->client_id]);
    $this->quotation->update(['lead_id' => $lead->id]);
    $seller = scopedUser(['quotations.view', 'quotations.update']);

    actingAs($seller)->patch(route('quotations.accept', $this->quotation))->assertForbidden();
    actingAs($this->user)->patch(route('quotations.accept', $this->quotation))->assertRedirect();

    expect($this->quotation->fresh()->status)->toBe(QuotationStatus::Accepted)
        ->and($lead->fresh()->statusCode())->toBe('won');
});

it('refuses every change to an accepted quotation', function () {
    actingAs($this->user)->patch(route('quotations.accept', $this->quotation));
    $q = $this->quotation->fresh();
    $reason = Lookup::query()->ofType(LookupType::RejectionReason)->value('id');

    actingAs($this->user)->put(route('quotations.update', $q), ['title_ar' => 'x'])->assertRedirect(route('quotations.show', $q));
    actingAs($this->user)->patch(route('quotations.reject', $q), ['rejection_reason_id' => $reason])->assertSessionHas('error', __('quotations.locked'));
    actingAs($this->user)->patch(route('quotations.accept', $q))->assertSessionHas('error', __('quotations.locked'));
    actingAs($this->user)->patch(route('quotations.send', $q))->assertSessionHas('error', __('quotations.locked'));
    actingAs($this->user)->post(route('quotations.revise', $q))->assertSessionHas('error', __('quotations.locked'));

    expect($q->fresh()->status)->toBe(QuotationStatus::Accepted)->and($q->fresh()->title_ar)->toBe('تشطيب داخلي')
        ->and(Quotation::query()->count())->toBe(1);

    actingAs($this->user)->post(route('quotations.copy', $q))->assertRedirect();
    actingAs($this->user)->get(route('quotations.show', $q))->assertOk();
    expect(Quotation::query()->count())->toBe(2);
});

it('rejects with a reason and logs the status change', function () {
    $reason = Lookup::query()->ofType(LookupType::RejectionReason)->value('id');

    actingAs($this->user)->patch(route('quotations.reject', $this->quotation), ['rejection_reason_id' => $reason, 'rejection_note' => 'السعر'])->assertRedirect();

    $q = $this->quotation->fresh();
    $log = Activity::query()->where('event', 'status_changed')->where('subject_id', $q->id)->latest('id')->first();

    expect($q->status)->toBe(QuotationStatus::Rejected)
        ->and($q->rejection_reason_id)->toBe($reason)
        ->and($log->properties['attributes']['status'])->toBe('rejected');
});

it('requires a reason to reject', function () {
    actingAs($this->user)->patch(route('quotations.reject', $this->quotation), [])->assertSessionHasErrors('rejection_reason_id');
});

it('sends the no-response reminder once after the rule days', function () {
    DueTasks::flush();
    SalesServiceProvider::registerDueTasks();
    actingAs($this->user)->patch(route('quotations.send', $this->quotation));

    $this->travel(2)->days();
    app(DueTasks::class)->runDue();
    expect($this->user->notifications()->count())->toBe(0);

    $this->travel(2)->days();
    app(DueTasks::class)->runDue();
    $this->travel(2)->hours();
    app(DueTasks::class)->runDue();

    expect($this->user->notifications()->where('type', 'quotation_no_response')->count())->toBe(1);
});

it('sends the expiring reminder once per validity date', function () {
    DueTasks::flush();
    SalesServiceProvider::registerDueTasks();
    $this->quotation->update(['valid_until' => '2026-11-16']);

    app(DueTasks::class)->runDue();
    $this->travel(2)->hours();
    app(DueTasks::class)->runDue();

    expect($this->user->notifications()->where('type', 'quotation_expiring')->count())->toBe(1);
});
