<?php

use App\Actions\AttachUploads;
use App\Enums\LookupType;
use App\Livewire\Tables\ClientsTable;
use App\Livewire\Tables\LeadsTable;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\Quotation;
use App\Services\Uploads\TemporaryUpload;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->engineer = scopedUser(['leads.view', 'leads.update', 'leads.delete', 'leads.export', 'clients.view'], 'assigned');
    $this->colleague = scopedUser(['leads.view'], 'assigned');
    $this->mine = Lead::factory()->count(2)->create(['assigned_to' => $this->engineer->id, 'contact_name' => 'طلب خاص بي']);
    $this->theirs = Lead::factory()->count(3)->create(['assigned_to' => $this->colleague->id, 'contact_name' => 'طلب زميل']);
});

it('lists only leads assigned to an assigned-scope user', function () {
    actingAs($this->engineer);

    expect(Livewire::test(LeadsTable::class)->viewData('rows')->total())->toBe(2);
});

it("returns 404 on another user's lead page and on its media", function () {
    Storage::fake('private');
    $lead = $this->theirs->first();
    $file = UploadedFile::fake()->image('site.jpg', 400, 300);
    $bytes = file_get_contents($file->getPathname());
    $upload = TemporaryUpload::create($this->colleague->id, strlen($bytes), 'site.jpg');
    file_put_contents($upload->path(), $bytes);
    $upload->saveMeta(['offset' => strlen($bytes)]);
    $media = app(AttachUploads::class)->handle($lead, [$upload->id], $this->colleague)->first();

    actingAs($this->engineer)->get(route('leads.show', $lead))->assertNotFound();
    actingAs($this->engineer)->get(route('media.show', $media))->assertNotFound();
    actingAs($this->engineer)->get(route('leads.show', $this->mine->first()))->assertOk();
});

it("keeps delete all and export inside the user's own leads", function () {
    actingAs($this->engineer);

    Livewire::test(LeadsTable::class)->call('deleteAll');
    expect(Lead::query()->count())->toBe(3);

    Lead::withTrashed()->restore();
    $component = Livewire::test(LeadsTable::class)->call('export');
    $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
    file_put_contents($path, base64_decode($component->effects['download']['content']));

    expect(SimpleExcelReader::create($path)->getRows()->count())->toBe(2);
});

it("cannot delete another user's lead by its id", function () {
    actingAs($this->engineer);

    Livewire::test(LeadsTable::class)->call('deleteOne', $this->theirs->first()->id);

    expect(Lead::query()->count())->toBe(5);
});

it("hides other users' leads from global search", function () {
    actingAs($this->engineer)->getJson('/search?q='.urlencode('طلب'))->assertOk()
        ->assertJsonCount(2, '0.results');
});

it('shows a client only through a lead assigned to the user', function () {
    $myClient = Client::factory()->create();
    $otherClient = Client::factory()->create();
    $this->mine->first()->update(['client_id' => $myClient->id]);
    $this->theirs->first()->update(['client_id' => $otherClient->id]);
    actingAs($this->engineer);

    expect(Livewire::test(ClientsTable::class)->viewData('rows')->total())->toBe(1);
    $this->get(route('clients.show', $otherClient))->assertNotFound();
});

it('refuses a client the user cannot see when creating a lead, visit or quotation', function () {
    $hidden = Client::factory()->create();
    $user = scopedUser(['leads.create', 'leads.view', 'site_visits.create', 'quotations.create'], 'assigned');
    $source = Lookup::query()->ofType(LookupType::LeadSource)->value('id');

    actingAs($user)->post(route('leads.store'), ['client_id' => $hidden->id, 'source_id' => $source])->assertSessionHasErrors('client_id');
    actingAs($user)->post(route('site-visits.store'), ['client_id' => $hidden->id, 'scheduled_at' => '2026-12-01 10:00', 'engineer_id' => $user->id])->assertSessionHasErrors('client_id');
    actingAs($user)->post(route('quotations.store'), [
        'client_id' => $hidden->id, 'title_ar' => 'x', 'issue_date' => '2026-11-14', 'valid_until' => '2026-12-14', 'assigned_to' => $user->id,
        'items' => [['description_ar' => 'x', 'quantity' => '1', 'unit_price' => '1']], 'payments' => [['label_ar' => 'x', 'percent' => '100']],
    ])->assertSessionHasErrors('client_id');

    expect($hidden->fresh()->isVisibleTo($user))->toBeFalse();
});

it('counts only visible quotations in the client page figures', function () {
    $client = Client::factory()->create();
    $this->mine->first()->update(['client_id' => $client->id]);
    Quotation::factory()->create(['client_id' => $client->id, 'assigned_to' => $this->engineer->id, 'status' => 'accepted', 'total' => '1000.00']);
    Quotation::factory()->create(['client_id' => $client->id, 'status' => 'accepted', 'total' => '99999.00']);

    actingAs($this->engineer)->get(route('clients.show', $client))->assertOk()
        ->assertSee(money('1000.00'))->assertDontSee(money('100999.00'))->assertDontSee(money('99999.00'));
});

it('shows every lead to a user with an all-scope role', function () {
    actingAs(scopedUser(['leads.view'], 'all'));

    expect(Livewire::test(LeadsTable::class)->viewData('rows')->total())->toBe(5);
});
