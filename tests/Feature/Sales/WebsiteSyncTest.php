<?php

use App\Models\Lead;
use App\Services\WebsiteSync;
use App\Settings\IntegrationSettings;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $settings = app(IntegrationSettings::class);
    $settings->website_url = 'https://class-skilled.example';
    $settings->api_key_hash = hash('sha256', 'shared-key');
    $settings->outgoing_key = 'shared-key';
    $settings->save();
});

function unsyncedPayload(array $files = []): array
{
    return ['data' => [
        ['external_id' => 'QR-0100', 'name' => 'سعيد', 'phone' => '0501110000', 'description' => 'ترميم', 'files' => $files],
        ['external_id' => 'QR-0101', 'name' => 'منى', 'phone' => '0502220000'],
    ]];
}

it('fetches unsynced requests, creates leads and confirms them to the website', function () {
    Http::fake([
        'class-skilled.example/api/system/quote-requests/unsynced' => Http::response(unsyncedPayload()),
        'class-skilled.example/api/system/quote-requests/synced' => Http::response(['ok' => true]),
    ]);

    expect(app(WebsiteSync::class)->fetch())->toBe(2)
        ->and(Lead::query()->count())->toBe(2)
        ->and(app(IntegrationSettings::class)->last_sync_count)->toBe(2)
        ->and(app(IntegrationSettings::class)->last_sync_status)->toBe('ok');

    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/synced')
        && $request['external_ids'] === ['QR-0100', 'QR-0101']
        && $request->hasHeader('X-Api-Key', 'shared-key'));
});

it('records a failed sync without throwing', function () {
    Http::fake(['*' => Http::response('down', 500)]);

    expect(app(WebsiteSync::class)->fetch())->toBe(0)
        ->and(app(IntegrationSettings::class)->last_sync_status)->not->toBe('ok')
        ->and(Lead::query()->count())->toBe(0);
});

it('downloads files only from the website host', function () {
    Http::fake([
        'class-skilled.example/api/system/quote-requests/unsynced' => Http::response(unsyncedPayload(['https://evil.example/x.jpg'])),
        'class-skilled.example/api/system/quote-requests/synced' => Http::response(['ok' => true]),
        'evil.example/*' => Http::response('nope'),
    ]);

    app(WebsiteSync::class)->fetch();

    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'evil.example'));
    expect(Lead::query()->count())->toBe(2);
});

it('generates a key once and stores only its hash', function () {
    $response = actingAs(scopedUser(['settings.manage']))->post(route('settings.integration.key'));

    $key = session('generated_key');
    $settings = app(IntegrationSettings::class);

    $response->assertRedirect();
    expect($key)->toBeString()->toHaveLength(48)
        ->and($settings->api_key_hash)->toBe(hash('sha256', $key))
        ->and($settings->outgoing_key)->toBe($key);
});

it('runs the fetch from the leads page', function () {
    Http::fake([
        'class-skilled.example/api/system/quote-requests/unsynced' => Http::response(['data' => []]),
        'class-skilled.example/api/system/quote-requests/synced' => Http::response(['ok' => true]),
    ]);

    actingAs(scopedUser(['leads.view', 'leads.create']))->post(route('leads.fetch-website'))->assertRedirect()->assertSessionHas('success');
});
