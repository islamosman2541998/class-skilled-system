<?php

use App\Actions\Quotations\SaveQuotation;
use App\Models\Client;
use App\Settings\SystemSettings;
use App\Support\WhatsApp;
use Database\Seeders\DocumentSequenceSeeder;
use Database\Seeders\LookupSeeder;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Carbon::setTestNow('2026-11-14 10:00');
    $this->seed([LookupSeeder::class, DocumentSequenceSeeder::class]);
    $this->user = scopedUser(['quotations.view', 'quotations.print', 'quotations.update']);
    $this->quotation = app(SaveQuotation::class)->handle([
        'client_id' => Client::factory()->create(['name_ar' => 'مؤسسة الريم', 'whatsapp' => '0501234567'])->id,
        'title_ar' => 'عزل سطح فيلا',
        'title_en' => 'Villa roof insulation',
        'issue_date' => '2026-11-14',
        'valid_until' => '2026-12-14',
        'assigned_to' => $this->user->id,
        'discount_type' => null,
        'discount_value' => '0',
        'items' => [
            ['section_ar' => 'العزل', 'description_ar' => 'عزل مائي بالبيتومين', 'description_en' => 'Bitumen waterproofing', 'quantity' => '420', 'unit_price' => '45'],
            ['section_ar' => 'العزل', 'description_ar' => 'طبقة حماية', 'quantity' => '1', 'unit_price' => '3500'],
        ],
        'payments' => [['label_ar' => 'مقدم', 'percent' => '30'], ['label_ar' => 'تسليم', 'percent' => '70']],
        'terms_ar' => 'الشروط',
    ], $this->user);
});

it('renders the quotation PDF in Arabic, English and both', function (string $lang) {
    $response = actingAs($this->user)->get(route('quotations.pdf', [$this->quotation, 'lang' => $lang]));

    $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
})->with(['ar', 'en', 'both']);

it('logs printing in the activity log', function () {
    actingAs($this->user)->get(route('quotations.pdf', $this->quotation));

    expect(Activity::query()->where('event', 'printed')->where('subject_id', $this->quotation->id)->count())->toBe(1);
});

it('needs quotations.print and visibility to print', function () {
    actingAs(scopedUser(['quotations.view']))->get(route('quotations.pdf', $this->quotation))->assertForbidden();
    actingAs(scopedUser(['quotations.view', 'quotations.print'], 'assigned'))->get(route('quotations.pdf', $this->quotation))->assertNotFound();
});

it('serves the PDF on a valid signed link without login and refuses an expired one', function () {
    $url = actingAs($this->user)->postJson(route('quotations.share', $this->quotation), ['lang' => 'ar'])->assertOk()->json('url');
    auth()->logout();

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get($url.'x')->assertForbidden();

    $this->travel(app(SystemSettings::class)->public_link_hours + 1)->hours();
    $this->get($url)->assertForbidden();
});

it('returns a share url and a WhatsApp link with the filled template', function () {
    $data = actingAs($this->user)->postJson(route('quotations.share', $this->quotation), ['lang' => 'ar'])->assertOk()->json();

    expect($data['whatsapp'])->toStartWith('https://wa.me/971501234567?text=');

    $text = rawurldecode(substr($data['whatsapp'], strlen('https://wa.me/971501234567?text=')));
    expect($text)->toContain('مؤسسة الريم')->toContain($this->quotation->number)->toContain($data['url']);
});

it('fills the quotation template placeholders', function () {
    $message = WhatsApp::quotationMessage($this->quotation->load('client'), 'en', 'https://example.test/q');

    expect($message)->toContain($this->quotation->number)->toContain('https://example.test/q')->toContain('AED');
});
