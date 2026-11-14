<?php

use App\Actions\Leads\EnsureClientForLead;
use App\Enums\LookupType;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-14 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['leads.view', 'leads.update']);
    $this->lead = Lead::factory()->create();
});

it('adds a call, message and note to the follow-up log with the author', function () {
    foreach (['call', 'message', 'note'] as $type) {
        actingAs($this->user)->post(route('leads.follow-ups.store', $this->lead), ['type' => $type, 'body' => 'تم الاتصال بالعميل'])->assertRedirect();
    }

    $entries = $this->lead->followUps()->get();

    expect($entries)->toHaveCount(3)
        ->and($entries->pluck('type')->map->value->all())->toEqualCanonicalizing(['call', 'message', 'note'])
        ->and($entries->first()->user_id)->toBe($this->user->id);
});

it('closes a lead as lost with a reason and logs the status change', function () {
    $reason = Lookup::query()->ofType(LookupType::RejectionReason)->value('id');

    actingAs($this->user)->patch(route('leads.close', $this->lead), ['rejection_reason_id' => $reason, 'rejection_note' => 'السعر أعلى من الميزانية'])->assertRedirect();

    $lead = $this->lead->fresh();
    $log = Activity::query()->where('event', 'status_changed')->latest('id')->first();

    expect($lead->statusCode())->toBe('lost')
        ->and($lead->rejection_reason_id)->toBe($reason)
        ->and($log->properties['old']['status'])->toBe('new')
        ->and($log->properties['attributes']['status'])->toBe('lost');
});

it('requires a rejection reason to close', function () {
    actingAs($this->user)->patch(route('leads.close', $this->lead), [])->assertSessionHasErrors('rejection_reason_id');
});

it('reopens a lost lead to contacted', function () {
    $this->lead->update(['status_id' => Lead::statusIdFor('lost')]);

    actingAs($this->user)->patch(route('leads.reopen', $this->lead))->assertRedirect();

    expect($this->lead->fresh()->statusCode())->toBe('contacted');
});

it('schedules the next follow-up and clears the overdue flag', function () {
    $this->lead->update(['follow_up_at' => now()->subDay()]);

    actingAs($this->user)->patch(route('leads.schedule', $this->lead), ['follow_up_at' => '2026-11-16 09:30'])->assertRedirect();

    expect($this->lead->fresh()->follow_up_at->format('Y-m-d H:i'))->toBe('2026-11-16 09:30');
});

it('assigns a user from the lead page and notifies them', function () {
    $other = scopedUser(['leads.view']);

    actingAs($this->user)->patch(route('leads.assign', $this->lead), ['assigned_to' => $other->id])->assertRedirect();

    expect($this->lead->fresh()->assigned_to)->toBe($other->id)->and($other->notifications()->count())->toBe(1);
});

it('creates the client from the lead data on first conversion', function () {
    $this->lead->update(['contact_name' => 'ناصر الشامسي', 'contact_phone' => '0509990001', 'contact_email' => 'n@x.ae']);

    $client = app(EnsureClientForLead::class)->handle($this->lead->fresh(), $this->user);

    expect($client->name_ar)->toBe('ناصر الشامسي')
        ->and($client->phone_normalized)->toBe('971509990001')
        ->and($this->lead->fresh()->client_id)->toBe($client->id)
        ->and($client->source_id)->toBe($this->lead->source_id);
});

it('reuses an existing client with the same phone instead of creating one', function () {
    $existing = Client::factory()->create(['phone' => '+971509990002']);
    $this->lead->update(['contact_phone' => '0509990002']);

    $client = app(EnsureClientForLead::class)->handle($this->lead->fresh(), $this->user);

    expect($client->id)->toBe($existing->id)->and(Client::query()->count())->toBe(1);
});

it('does not move a lost lead forward', function () {
    $this->lead->update(['status_id' => Lead::statusIdFor('lost')]);

    $this->lead->fresh()->setStatusCode('quoted', $this->user);

    expect($this->lead->fresh()->statusCode())->toBe('lost');
});

it('refuses actions on a lead the user cannot see', function () {
    $engineer = scopedUser(['leads.view', 'leads.update'], 'assigned');

    actingAs($engineer)->post(route('leads.follow-ups.store', $this->lead), ['type' => 'note', 'body' => 'x'])->assertNotFound();
});
