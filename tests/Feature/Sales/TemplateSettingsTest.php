<?php

use App\Settings\TemplateSettings;

use function Pest\Laravel\actingAs;

function templatesPayload(array $overrides = []): array
{
    $s = app(TemplateSettings::class);

    return array_replace_recursive([
        'quotation_terms_ar' => $s->quotation_terms_ar,
        'quotation_terms_en' => $s->quotation_terms_en,
        'quotation_footer_ar' => '',
        'quotation_footer_en' => '',
        'whatsapp_quotation_ar' => $s->whatsapp_quotation_ar,
        'whatsapp_quotation_en' => $s->whatsapp_quotation_en,
        'payment_templates' => $s->payment_templates,
    ], $overrides);
}

it('has the default 30/40/30 payment template and Arabic WhatsApp text', function () {
    $s = app(TemplateSettings::class);

    expect(array_column($s->payment_templates[0]['lines'], 'percent'))->toBe(['30.00', '40.00', '30.00'])
        ->and($s->whatsapp_quotation_ar)->toContain(':link')->toContain(':number')
        ->and($s->quotation_terms_ar)->not->toBe('');
});

it('saves terms and a new payment template', function () {
    $payload = templatesPayload(['quotation_terms_ar' => 'شروط جديدة']);
    $payload['payment_templates'][] = ['name_ar' => 'دفعتان', 'name_en' => 'Two payments', 'lines' => [
        ['label_ar' => 'مقدم', 'label_en' => 'Advance', 'percent' => '50'],
        ['label_ar' => 'عند التسليم', 'label_en' => 'On handover', 'percent' => '50'],
    ]];

    actingAs(scopedUser(['settings.manage']))->put(route('settings.templates.update'), $payload)->assertRedirect()->assertSessionHasNoErrors();

    $s = app(TemplateSettings::class);
    expect($s->quotation_terms_ar)->toBe('شروط جديدة')
        ->and($s->payment_templates)->toHaveCount(2)
        ->and($s->payment_templates[1]['lines'][0]['percent'])->toBe('50.00');
});

it('rejects a payment template whose lines do not sum to 100', function () {
    $payload = templatesPayload();
    $payload['payment_templates'][0]['lines'][0]['percent'] = '35';

    actingAs(scopedUser(['settings.manage']))->put(route('settings.templates.update'), $payload)->assertSessionHasErrors('payment_templates.0');
});

it('rejects a WhatsApp template without the :link placeholder', function () {
    actingAs(scopedUser(['settings.manage']))->put(route('settings.templates.update'), templatesPayload(['whatsapp_quotation_ar' => 'مرحبا']))
        ->assertSessionHasErrors('whatsapp_quotation_ar');
});

it('returns 403 without settings.manage', function () {
    actingAs(scopedUser())->get(route('settings.templates'))->assertForbidden();
});

it('renders the templates screen', function () {
    actingAs(scopedUser(['settings.manage']))->get(route('settings.templates'))->assertOk()->assertSee(__('settings.groups.templates'));
});
