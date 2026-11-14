<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

/**
 * Document templates (spec §5.10, §7): quotation terms, footer, WhatsApp text, payment schedules.
 * Every value is copied into the document when it is created and stays editable there.
 */
class TemplateSettings extends Settings
{
    public string $quotation_terms_ar;

    public string $quotation_terms_en;

    public ?string $quotation_footer_ar;

    public ?string $quotation_footer_en;

    /** Placeholders :client, :number, :total, :link. */
    public string $whatsapp_quotation_ar;

    public string $whatsapp_quotation_en;

    // Payment schedule templates: name_ar, name_en and lines of label_ar, label_en, percent (sum 100).
    // No @var on purpose: spatie/laravel-settings cannot read nested array shapes in docblocks.
    public array $payment_templates;

    public static function group(): string
    {
        return 'templates';
    }
}
