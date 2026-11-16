<?php

namespace App\Support;

use App\Models\Claim;
use App\Models\Client;
use App\Models\Invoice;
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

        return strtr($template, [
            ':client' => self::clientName($quotation->client, $language),
            ':number' => $quotation->number,
            ':total' => self::amount((string) $quotation->total, $language),
            ':link' => $url,
        ]);
    }

    public static function invoiceMessage(Invoice $invoice, string $language, string $url): string
    {
        $templates = app(TemplateSettings::class);
        $template = $language === 'en' ? $templates->whatsapp_invoice_en : $templates->whatsapp_invoice_ar;

        return strtr($template, [
            ':client' => self::clientName($invoice->client, $language),
            ':number' => (string) $invoice->number,
            ':total' => self::amount((string) $invoice->total, $language),
            ':due' => fmt_date($invoice->due_date),
            ':link' => $url,
        ]);
    }

    public static function claimMessage(Claim $claim, string $language, string $url): string
    {
        $templates = app(TemplateSettings::class);
        $template = $language === 'en' ? $templates->whatsapp_claim_en : $templates->whatsapp_claim_ar;

        return strtr($template, [
            ':client' => self::clientName($claim->client, $language),
            ':number' => (string) $claim->number,
            ':total' => self::amount((string) $claim->total, $language),
            ':link' => $url,
        ]);
    }

    /** An amount with the currency symbol of the language, kept on one line. */
    public static function amount(string $value, string $language): string
    {
        $finance = app(FinanceSettings::class);

        return money($value, false)."\u{00A0}".($language === 'en' ? $finance->currency_symbol_en : $finance->currency_symbol_ar);
    }

    public static function clientName(Client $client, string $language): string
    {
        return $language === 'en' && filled($client->name_en) ? (string) $client->name_en : $client->name_ar;
    }
}
