<?php

namespace App\Support;

use App\Models\Quotation;
use App\Settings\FinanceSettings;
use App\Settings\TemplateSettings;

/**
 * "Send by WhatsApp" links (spec §5.10): a wa.me chat with the client and a message from the
 * template in the settings.
 */
final class WhatsApp
{
    public static function link(?string $phone, string $message): string
    {
        $digits = Phone::normalize($phone);

        return 'https://wa.me/'.($digits ?? '').'?text='.rawurlencode($message);
    }

    public static function quotationMessage(Quotation $quotation, string $language, string $url): string
    {
        $templates = app(TemplateSettings::class);
        $template = $language === 'en' ? $templates->whatsapp_quotation_en : $templates->whatsapp_quotation_ar;
        $finance = app(FinanceSettings::class);
        $symbol = $language === 'en' ? $finance->currency_symbol_en : $finance->currency_symbol_ar;
        $client = $language === 'en' && filled($quotation->client->name_en) ? (string) $quotation->client->name_en : $quotation->client->name_ar;

        return strtr($template, [
            ':client' => $client,
            ':number' => $quotation->number,
            ':total' => money($quotation->total, false)."\u{00A0}".$symbol,
            ':link' => $url,
        ]);
    }
}
