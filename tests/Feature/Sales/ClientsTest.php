<?php

use App\Enums\ClientType;
use App\Enums\LookupType;
use App\Livewire\Tables\ClientsTable;
use App\Models\Client;
use App\Models\Lookup;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

function clientPayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'company',
        'name_ar' => 'مؤسسة الريم للتطوير العقاري',
        'name_en' => 'Al Reem Development',
        'phone' => '0501234567',
        'email' => 'info@alreem.ae',
    ], $overrides);
}

it('returns 403 on client pages without clients.view', function () {
    actingAs(scopedUser())->get(route('clients.index'))->assertForbidden();
    actingAs(scopedUser())->get(route('clients.show', Client::factory()->create()))->assertForbidden();
});

it('creates a company client with contacts and stores the normalized phone', function () {
    $response = actingAs(scopedUser(['clients.view', 'clients.create']))->post(route('clients.store'), clientPayload([
        'contacts' => [['name' => 'سالم الكعبي', 'position' => 'مدير المشاريع', 'phone' => '0559876543']],
    ]));

    $client = Client::query()->firstOrFail();
    $response->assertRedirect(route('clients.show', $client));

    expect($client->type)->toBe(ClientType::Company)
        ->and($client->phone_normalized)->toBe('971501234567')
        ->and($client->contacts)->toHaveCount(1)
        ->and($client->contacts->first()->name)->toBe('سالم الكعبي');
});

it('reports a duplicate phone written differently', function () {
    $existing = Client::factory()->create(['phone' => '0501234567']);

    actingAs(scopedUser(['clients.view']))
        ->getJson(route('clients.duplicates', ['phone' => '+971 50 123 4567']))
        ->assertOk()->assertJsonCount(1)->assertJsonPath('0.name', $existing->name_ar);
});

it('excludes the client itself from duplicates when editing', function () {
    $client = Client::factory()->create(['phone' => '0501234567']);

    actingAs(scopedUser(['clients.view']))
        ->getJson(route('clients.duplicates', ['phone' => '0501234567', 'ignore' => $client->id]))
        ->assertOk()->assertJsonCount(0);
});

it('requires name_ar and phone, and a 15-digit trn when given', function () {
    $user = scopedUser(['clients.view', 'clients.create']);

    actingAs($user)->post(route('clients.store'), clientPayload(['name_ar' => '', 'phone' => '']))->assertSessionHasErrors(['name_ar', 'phone']);
    actingAs($user)->post(route('clients.store'), clientPayload(['trn' => '12345']))->assertSessionHasErrors('trn');
    actingAs($user)->post(route('clients.store'), clientPayload(['trn' => '100123456700003']))->assertSessionHasNoErrors();
});

it('creates a client from the quick-add modal as JSON', function () {
    $user = scopedUser(['clients.view', 'clients.create']);

    actingAs($user)->postJson(route('clients.quick-store'), clientPayload(['type' => 'individual', 'name_ar' => 'خالد المنصوري']))
        ->assertCreated()->assertJsonPath('name', 'خالد المنصوري');
    actingAs($user)->postJson(route('clients.quick-store'), clientPayload(['name_ar' => '']))
        ->assertUnprocessable()->assertJsonValidationErrors('name_ar');
});

it('searches clients by name, phone and trn in the table and in global search', function () {
    Client::factory()->create(['name_ar' => 'شركة الواحة', 'phone' => '0501234567']);
    Client::factory()->create(['name_ar' => 'مؤسسة النخيل', 'phone' => '0567777777', 'trn' => '100999888777666']);
    actingAs(scopedUser(['clients.view']));

    expect(Livewire::test(ClientsTable::class)->set('search', '050 123 4567')->viewData('rows')->total())->toBe(1)
        ->and(Livewire::test(ClientsTable::class)->set('search', '100999888')->viewData('rows')->total())->toBe(1);

    $this->getJson('/search?q='.urlencode('+971501234567'))->assertJsonPath('0.key', 'clients')->assertJsonPath('0.results.0.title', 'شركة الواحة');
});

it('filters clients by type, source and emirate', function () {
    $dubai = Lookup::factory()->create(['type' => LookupType::Emirate, 'name_ar' => 'دبي']);
    Client::factory()->create(['type' => 'company', 'emirate_id' => $dubai->id]);
    Client::factory()->count(2)->create(['type' => 'individual']);
    actingAs(scopedUser(['clients.view']));

    expect(Livewire::test(ClientsTable::class)->set('filters.type', 'individual')->viewData('rows')->total())->toBe(2)
        ->and(Livewire::test(ClientsTable::class)->set('filters.emirate_id', (string) $dubai->id)->viewData('rows')->total())->toBe(1);
});

it('soft deletes a client without linked records', function () {
    $client = Client::factory()->create();
    actingAs(scopedUser(['clients.view', 'clients.delete']));

    Livewire::test(ClientsTable::class)->call('deleteOne', $client->id);

    expect(Client::query()->count())->toBe(0)->and(Client::withTrashed()->count())->toBe(1);
});

it('shows the client page with its tabs and attachments', function () {
    $client = Client::factory()->create(['name_ar' => 'شركة الأفق']);

    actingAs(scopedUser(['clients.view', 'clients.update']))->get(route('clients.show', $client))
        ->assertOk()->assertSee('شركة الأفق')->assertSee(__('clients.tabs.contacts'))->assertSee(__('uploads.attachments'));
});

it('manages contacts from the client page', function () {
    $client = Client::factory()->create();
    $user = scopedUser(['clients.view', 'clients.update']);

    actingAs($user)->post(route('clients.contacts.store', $client), ['name' => 'مريم', 'phone' => '0501112222'])->assertRedirect();
    $contact = $client->contacts()->firstOrFail();
    actingAs($user)->put(route('clients.contacts.update', [$client, $contact]), ['name' => 'مريم الظاهري'])->assertRedirect();
    expect($contact->fresh()->name)->toBe('مريم الظاهري');
    actingAs($user)->delete(route('clients.contacts.destroy', [$client, $contact]))->assertRedirect();
    expect($client->contacts()->count())->toBe(0);
});
