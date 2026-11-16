<?php

use App\Enums\ClaimStatus;
use App\Models\Claim;
use App\Models\Project;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['projects.view', 'claims.view', 'claims.print']);
    $project = Project::factory()->create(['billing_method' => 'claims', 'title_en' => 'Warehouse']);
    $this->claim = Claim::factory()->for($project)->create([
        'number' => 'PC-2026-0003', 'status' => ClaimStatus::Submitted, 'client_id' => $project->client_id,
        'work_current' => '1000.00', 'gross_amount' => '1000.00', 'retention_amount' => '50.00', 'net_amount' => '950.00', 'vat_amount' => '47.50', 'total' => '997.50',
    ]);
    $this->claim->lines()->create([
        'description_ar' => 'بلاط', 'description_en' => 'Tiles', 'contract_quantity' => '100', 'unit_price' => '50',
        'previous_quantity' => '10', 'current_quantity' => '20', 'cumulative_quantity' => '30', 'current_amount' => '1000', 'cumulative_amount' => '1500', 'sort_order' => 1,
    ]);
});

it('renders the claim PDF in ar, en and both', function (string $lang) {
    $response = actingAs($this->user)->get(route('claims.pdf', [$this->claim, 'lang' => $lang]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and(Activity::query()->where('event', 'printed')->where('subject_id', $this->claim->id)->count())->toBe(1);
})->with(['ar', 'en', 'both']);

it('does not print drafts and needs claims.print', function () {
    $draft = Claim::factory()->create();

    actingAs($this->user)->get(route('claims.pdf', $draft))->assertNotFound();
    actingAs(scopedUser(['projects.view', 'claims.view']))->get(route('claims.pdf', $this->claim))->assertForbidden();
});

it('signed link opens the PDF and an altered one is 403', function () {
    $response = actingAs($this->user)->postJson(route('claims.share', $this->claim), ['lang' => 'ar'])->assertOk();

    expect($response->json('whatsapp'))->toStartWith('https://wa.me/')
        ->and(urldecode((string) $response->json('whatsapp')))->toContain('PC-2026-0003');

    auth()->logout();
    get($response->json('url'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    get(str_replace('lang=ar', 'lang=en', (string) $response->json('url')))->assertForbidden();
});
