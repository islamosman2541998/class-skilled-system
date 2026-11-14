<?php

use App\Enums\LookupType;
use App\Livewire\Tables\LeadsTable;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-14 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
});

function leadPayload(array $overrides = []): array
{
    return array_merge([
        'contact_name' => 'خالد المنصوري',
        'contact_phone' => '0501234567',
        'service_id' => Lookup::query()->ofType(LookupType::Service)->value('id'),
        'source_id' => Lookup::query()->ofType(LookupType::LeadSource)->where('code', 'website')->value('id'),
        'description' => 'عزل سطح فيلا 400 م²',
    ], $overrides);
}

it('creates a lead with number LD-2026-0001 and status new', function () {
    actingAs(scopedUser(['leads.view', 'leads.create']))->post(route('leads.store'), leadPayload())->assertRedirect();

    $lead = Lead::query()->firstOrFail();

    expect($lead->number)->toBe('LD-2026-0001')
        ->and($lead->statusCode())->toBe('new')
        ->and($lead->contact_phone_normalized)->toBe('971501234567');
});

it('accepts a lead for an existing client or for new contact data', function () {
    $client = Client::factory()->create();
    $user = scopedUser(['leads.view', 'leads.create']);

    actingAs($user)->post(route('leads.store'), leadPayload(['client_id' => $client->id, 'contact_name' => '', 'contact_phone' => '']))->assertSessionHasNoErrors();
    actingAs($user)->post(route('leads.store'), leadPayload(['contact_name' => '', 'contact_phone' => '']))->assertSessionHasErrors(['contact_name', 'contact_phone']);

    expect(Lead::query()->first()->client_id)->toBe($client->id);
});

it('notifies the assigned user, not the acting user', function () {
    $actor = scopedUser(['leads.view', 'leads.create', 'leads.update']);
    $other = scopedUser(['leads.view']);

    actingAs($actor)->post(route('leads.store'), leadPayload(['assigned_to' => $other->id]));
    actingAs($actor)->post(route('leads.store'), leadPayload(['assigned_to' => $actor->id]));

    expect($other->notifications()->count())->toBe(1)
        ->and($actor->notifications()->count())->toBe(0)
        ->and($other->notifications()->first()->data['params']['number'])->toBe('LD-2026-0001');
});

it('notifies again when the assignee changes on update', function () {
    $actor = scopedUser(['leads.view', 'leads.update']);
    $first = scopedUser(['leads.view']);
    $second = scopedUser(['leads.view']);
    $lead = Lead::factory()->create(['assigned_to' => $first->id]);

    actingAs($actor)->put(route('leads.update', $lead), leadPayload(['assigned_to' => $second->id, 'status_id' => $lead->status_id]))->assertRedirect();

    expect($second->notifications()->count())->toBe(1)->and($first->notifications()->count())->toBe(0);
});

it('filters overdue follow-ups and excludes closed leads', function () {
    $lost = Lookup::query()->ofType(LookupType::LeadStatus)->where('code', 'lost')->value('id');
    Lead::factory()->create(['follow_up_at' => now()->subDay()]);
    Lead::factory()->create(['follow_up_at' => now()->subDay(), 'status_id' => $lost]);
    Lead::factory()->create(['follow_up_at' => now()->addDay()]);
    actingAs(scopedUser(['leads.view']));

    expect(Livewire::test(LeadsTable::class)->set('filters.overdue', '1')->viewData('rows')->total())->toBe(1);
});

it('searches by number, name and a phone written differently', function () {
    Lead::factory()->create(['contact_name' => 'سعيد الكتبي', 'contact_phone' => '0507654321']);
    Lead::factory()->create(['contact_name' => 'آخر']);
    actingAs(scopedUser(['leads.view']));

    expect(Livewire::test(LeadsTable::class)->set('search', 'الكتبي')->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(LeadsTable::class)->set('search', '+971 50 765 4321')->viewData('rows')->total())->toBe(1);
});

it('blocks deleting a client that has a lead', function () {
    $lead = Lead::factory()->create(['client_id' => Client::factory()->create()->id]);

    expect($lead->client->deletionBlockedReason(scopedUser()))->toBe(__('clients.has_records'));
});

it('refuses to delete a lookup used by a lead', function () {
    $lead = Lead::factory()->create();

    expect(Lookup::query()->findOrFail($lead->service_id)->deletionBlockedReason(scopedUser()))->not->toBeNull();
});

it('shows the lead page', function () {
    $lead = Lead::factory()->create(['contact_name' => 'ليلى المزروعي']);

    actingAs(scopedUser(['leads.view']))->get(route('leads.show', $lead))->assertOk()->assertSee('ليلى المزروعي')->assertSee($lead->number);
});
