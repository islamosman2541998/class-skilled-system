<?php

use App\Enums\LookupType;
use App\Models\Lead;
use App\Models\Lookup;
use App\Models\User;
use App\Settings\IntegrationSettings;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $settings = app(IntegrationSettings::class);
    $settings->api_key_hash = hash('sha256', 'test-website-key');
    $settings->save();
    $this->sales = User::factory()->create()->assignRole('sales');
    RateLimiter::clear('website');
});

function websitePayload(array $overrides = []): array
{
    return array_merge([
        'external_id' => 'QR-0042',
        'name' => 'عبدالله الحمادي',
        'phone' => '+971 50 777 8899',
        'email' => 'a@example.com',
        'service' => 'Epoxy flooring',
        'emirate' => 'دبي',
        'area_text' => 'الخليج التجاري',
        'area_m2' => '350',
        'start_when' => 'خلال شهر',
        'description' => 'أرضية إيبوكسي لمستودع',
        'locale' => 'ar',
    ], $overrides);
}

function postLead(array $payload, string $key = 'test-website-key')
{
    return test()->withHeaders(['X-Api-Key' => $key, 'Accept' => 'application/json'])->post('/api/v1/website/leads', $payload);
}

it('creates a lead with source website, status new and notifies sales', function () {
    postLead(websitePayload())->assertCreated()->assertJsonStructure(['id', 'number']);

    $lead = Lead::query()->firstOrFail();

    expect($lead->external_id)->toBe('QR-0042')
        ->and($lead->statusCode())->toBe('new')
        ->and($lead->source->code)->toBe('website')
        ->and($lead->contact_phone_normalized)->toBe('971507778899')
        ->and($this->sales->notifications()->count())->toBe(1);
});

it('returns the same lead for a repeated external_id without a second notification or attachment', function () {
    Storage::fake('private');
    $first = postLead([...websitePayload(), 'files' => [UploadedFile::fake()->image('a.jpg', 300, 200)]])->assertCreated()->json('id');
    $second = postLead([...websitePayload(), 'files' => [UploadedFile::fake()->image('a.jpg', 300, 200)]])->assertOk()->json('id');

    expect($second)->toBe($first)
        ->and(Lead::query()->count())->toBe(1)
        ->and(Lead::query()->first()->getMedia('attachments'))->toHaveCount(1)
        ->and($this->sales->notifications()->count())->toBe(1);
});

it('rejects a missing or wrong key with 401', function () {
    postLead(websitePayload(), 'wrong')->assertUnauthorized();
    test()->postJson('/api/v1/website/leads', websitePayload())->assertUnauthorized();
    expect(Lead::query()->count())->toBe(0);
});

it('refuses every request while no key is configured', function () {
    $settings = app(IntegrationSettings::class);
    $settings->api_key_hash = null;
    $settings->save();

    postLead(websitePayload(), '')->assertUnauthorized();
});

it('rate limits after 30 requests a minute', function () {
    for ($i = 1; $i <= 30; $i++) {
        postLead(websitePayload(['external_id' => 'QR-'.$i]));
    }

    postLead(websitePayload(['external_id' => 'QR-31']))->assertStatus(429);
});

it('stores valid files and rejects a fake image with 422', function () {
    Storage::fake('private');

    postLead([...websitePayload(['external_id' => 'QR-1']), 'files' => [UploadedFile::fake()->createWithContent('plan.jpg', '<?php echo 1;')]])->assertUnprocessable();
    expect(Lead::query()->count())->toBe(0);

    postLead([...websitePayload(['external_id' => 'QR-2']), 'files' => [UploadedFile::fake()->image('site.png', 400, 300)]])->assertCreated();
    expect(Lead::query()->first()->getMedia('attachments'))->toHaveCount(1);
});

it('matches service and emirate by name and keeps unknown ones in the description', function () {
    postLead(websitePayload(['external_id' => 'QR-1']))->assertCreated();
    postLead(websitePayload(['external_id' => 'QR-2', 'service' => 'خدمة غير معروفة', 'emirate' => 'Mars']))->assertCreated();

    $known = Lead::query()->where('external_id', 'QR-1')->first();
    $unknown = Lead::query()->where('external_id', 'QR-2')->first();

    expect($known->service_id)->toBe(Lookup::query()->ofType(LookupType::Service)->where('name_en', 'Epoxy flooring')->value('id'))
        ->and($known->emirate_id)->toBe(Lookup::query()->ofType(LookupType::Emirate)->where('name_ar', 'دبي')->value('id'))
        ->and($unknown->service_id)->toBeNull()
        ->and($unknown->description)->toContain('خدمة غير معروفة')->toContain('Mars');
});

it('validates the phone and the external id', function () {
    postLead(websitePayload(['phone' => '', 'external_id' => '']))->assertUnprocessable()->assertJsonValidationErrors(['phone', 'external_id']);
});
