<?php

use App\Enums\SiteVisitStatus;
use App\Models\Client;
use App\Models\Lead;
use App\Models\SiteVisit;
use App\Providers\SalesServiceProvider;
use App\Services\DueTasks;
use App\Services\Uploads\TemporaryUpload;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-14 08:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->sales = scopedUser(['site_visits.view', 'site_visits.create', 'site_visits.update', 'leads.view']);
    $this->engineer = scopedUser(['site_visits.view', 'site_visits.update'], 'assigned');
});

it('books a visit from a lead, creates the client and moves the lead to visit', function () {
    $lead = Lead::factory()->create(['contact_name' => 'حمد البلوشي', 'contact_phone' => '0508887766']);

    actingAs($this->sales)->post(route('site-visits.store'), [
        'lead_id' => $lead->id,
        'scheduled_at' => '2026-11-15 10:00',
        'engineer_id' => $this->engineer->id,
        'address' => 'خليفة أ، أبوظبي',
        'map_url' => 'https://maps.google.com/?q=24.4,54.6',
    ])->assertRedirect();

    $visit = SiteVisit::query()->firstOrFail();
    $lead->refresh();

    expect($visit->number)->toBe('SV-2026-0001')
        ->and($visit->status)->toBe(SiteVisitStatus::Scheduled)
        ->and($lead->statusCode())->toBe('visit')
        ->and($visit->client_id)->toBe($lead->client_id)
        ->and(Client::query()->find($lead->client_id)->name_ar)->toBe('حمد البلوشي');
});

it('notifies the engineer when assigned', function () {
    actingAs($this->sales)->post(route('site-visits.store'), [
        'client_id' => Client::factory()->create()->id,
        'scheduled_at' => '2026-11-15 10:00',
        'engineer_id' => $this->engineer->id,
    ]);

    expect($this->engineer->notifications()->count())->toBe(1);
});

it('shows only my scheduled visits of today and later on my visits', function () {
    $other = scopedUser(['site_visits.view'], 'assigned');
    SiteVisit::factory()->create(['engineer_id' => $this->engineer->id, 'scheduled_at' => '2026-11-14 11:00', 'address' => 'زيارة اليوم']);
    SiteVisit::factory()->create(['engineer_id' => $this->engineer->id, 'scheduled_at' => '2026-11-17 09:00', 'address' => 'زيارة قادمة']);
    SiteVisit::factory()->create(['engineer_id' => $this->engineer->id, 'scheduled_at' => '2026-11-13 09:00', 'address' => 'زيارة أمس']);
    SiteVisit::factory()->create(['engineer_id' => $other->id, 'scheduled_at' => '2026-11-14 12:00', 'address' => 'زيارة زميل']);

    actingAs($this->engineer)->get(route('my-visits'))->assertOk()
        ->assertSee('زيارة اليوم')->assertSee('زيارة قادمة')
        ->assertDontSee('زيارة أمس')->assertDontSee('زيارة زميل');
});

it('records a result with photos from the engineer screen', function () {
    Storage::fake('private');
    $visit = SiteVisit::factory()->create(['engineer_id' => $this->engineer->id]);
    $file = UploadedFile::fake()->image('roof.jpg', 640, 480);
    $bytes = file_get_contents($file->getPathname());
    $upload = TemporaryUpload::create($this->engineer->id, strlen($bytes), 'roof.jpg');
    file_put_contents($upload->path(), $bytes);
    $upload->saveMeta(['offset' => strlen($bytes)]);

    actingAs($this->engineer)->put(route('site-visits.result.store', $visit), [
        'status' => 'done',
        'notes' => 'السطح يحتاج عزل مائي كامل',
        'measurements' => 'المساحة 420 م²',
        'uploads' => [$upload->id],
    ])->assertRedirect(route('my-visits'));

    $visit->refresh();

    expect($visit->status)->toBe(SiteVisitStatus::Done)
        ->and($visit->result_at)->not->toBeNull()
        ->and($visit->getMedia('attachments'))->toHaveCount(1);
});

it('lets an engineer open only their own visits', function () {
    $mine = SiteVisit::factory()->create(['engineer_id' => $this->engineer->id]);
    $theirs = SiteVisit::factory()->create(['engineer_id' => scopedUser()->id]);

    actingAs($this->engineer)->get(route('site-visits.show', $mine))->assertOk();
    actingAs($this->engineer)->get(route('site-visits.show', $theirs))->assertNotFound();
    actingAs($this->engineer)->put(route('site-visits.result.store', $theirs), ['status' => 'done'])->assertNotFound();
});

it('sends the visit-today notification once per day', function () {
    DueTasks::flush();
    SalesServiceProvider::registerDueTasks();
    SiteVisit::factory()->create(['engineer_id' => $this->engineer->id, 'scheduled_at' => '2026-11-14 13:00']);

    app(DueTasks::class)->runDue();
    $this->travel(61)->minutes();
    app(DueTasks::class)->runDue();
    expect($this->engineer->notifications()->count())->toBe(1);

    SiteVisit::factory()->create(['engineer_id' => $this->engineer->id, 'scheduled_at' => '2026-11-15 09:00']);
    $this->travelTo(Carbon::parse('2026-11-15 07:00'));
    app(DueTasks::class)->runDue();
    expect($this->engineer->notifications()->count())->toBe(2);
});

it('rejects a map link that is not https', function () {
    actingAs($this->sales)->post(route('site-visits.store'), [
        'client_id' => Client::factory()->create()->id,
        'scheduled_at' => '2026-11-15 10:00',
        'engineer_id' => $this->engineer->id,
        'map_url' => 'javascript:alert(1)',
    ])->assertSessionHasErrors('map_url');
});

it('refuses to delete a client that has a visit', function () {
    $visit = SiteVisit::factory()->create();

    expect($visit->client->deletionBlockedReason(scopedUser()))->toBe(__('clients.has_records'));
});
