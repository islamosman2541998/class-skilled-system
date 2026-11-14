<?php

use App\Models\Invoice;
use App\Models\Project;
use App\Settings\CompanySettings;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    Carbon::setTestNow('2026-11-16 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class, RolesSeeder::class]);
    $this->user = scopedUser(['invoices.view', 'invoices.print', 'projects.view']);
    $this->invoice = Invoice::factory()->for(Project::factory())->issued()->create(['number' => 'INV-2026-0007']);
    $this->invoice->items()->create(['description_ar' => 'دفعة مقدمة', 'description_en' => 'Advance payment', 'quantity' => '1', 'unit_price' => '1000', 'line_total' => '1000.00', 'sort_order' => 1]);
});

it('renders the tax invoice PDF in three languages and logs printing', function (string $lang) {
    $company = app(CompanySettings::class);
    $company->trn = '100123456700003';
    $company->save();

    $response = actingAs($this->user)->get(route('invoices.pdf', [$this->invoice, 'lang' => $lang]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF')
        ->and(Activity::query()->where('event', 'printed')->where('subject_id', $this->invoice->id)->count())->toBe(1);
})->with(['ar', 'en', 'both']);

it('does not print drafts', function () {
    $draft = Invoice::factory()->for(Project::factory())->create();

    actingAs($this->user)->get(route('invoices.pdf', $draft))->assertNotFound();
});

it('needs invoices.print', function () {
    actingAs(scopedUser(['invoices.view']))->get(route('invoices.pdf', $this->invoice))->assertForbidden();
});

it('shares a signed WhatsApp link that opens the PDF and refuses an altered one', function () {
    $response = actingAs($this->user)->postJson(route('invoices.share', $this->invoice), ['lang' => 'ar'])->assertOk();

    expect($response->json('whatsapp'))->toStartWith('https://wa.me/')
        ->and(urldecode((string) $response->json('whatsapp')))->toContain('INV-2026-0007');

    auth()->logout();
    get($response->json('url'))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    get(str_replace('lang=ar', 'lang=en', (string) $response->json('url')))->assertForbidden();
});

it('signed links expire', function () {
    $url = URL::temporarySignedRoute('public.invoice', now()->addHour(), ['invoice' => $this->invoice->id, 'lang' => 'ar']);

    $this->travel(2)->hours();

    get($url)->assertForbidden();
});
