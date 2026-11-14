<?php

namespace App\Http\Controllers;

use App\Models\Receipt;
use App\Services\ActivityLogger;
use App\Services\Pdf\PdfRenderer;
use App\Settings\SystemSettings;
use App\Settings\TemplateSettings;
use App\Support\WhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * The receipt voucher PDF and its WhatsApp link (spec §5.10).
 */
class ReceiptPdfController extends Controller
{
    public function show(Request $request, Receipt $receipt, PdfRenderer $pdf): Response
    {
        Gate::authorize('receipts.print');
        abort_unless($receipt->isVisibleTo($request->user()), 404);

        $language = in_array($request->query('lang'), PdfRenderer::LANGUAGES, true) ? (string) $request->query('lang') : app()->getLocale();
        ActivityLogger::record('printed', $receipt, ['language' => $language], 'receipts');

        return self::pdfResponse($receipt, $pdf, $language, $request->boolean('download'));
    }

    public function share(Request $request, Receipt $receipt, SystemSettings $system, TemplateSettings $templates): JsonResponse
    {
        Gate::authorize('receipts.print');
        abort_unless($receipt->isVisibleTo($request->user()), 404);

        $data = $request->validate(['lang' => ['nullable', Rule::in(PdfRenderer::LANGUAGES)]]);
        $language = $data['lang'] ?? 'ar';
        $url = URL::temporarySignedRoute('public.receipt', now()->addHours($system->public_link_hours), ['receipt' => $receipt->id, 'lang' => $language]);
        $messageLanguage = $language === 'en' ? 'en' : 'ar';

        $message = strtr($messageLanguage === 'en' ? $templates->whatsapp_receipt_en : $templates->whatsapp_receipt_ar, [
            ':client' => WhatsApp::clientName($receipt->client, $messageLanguage),
            ':number' => $receipt->number,
            ':total' => WhatsApp::amount((string) $receipt->amount, $messageLanguage),
            ':link' => $url,
        ]);

        ActivityLogger::record('shared', $receipt, ['language' => $language], 'receipts');

        return response()->json(['url' => $url, 'whatsapp' => WhatsApp::link($receipt->client->whatsapp ?: $receipt->client->phone, $message)]);
    }

    public static function pdfResponse(Receipt $receipt, PdfRenderer $pdf, string $language, bool $download = false): Response
    {
        $receipt->loadMissing(['client', 'project', 'paymentMethod', 'receiver', 'allocations.invoice']);

        $bytes = $pdf->render('pdf.receipt', ['receipt' => $receipt], $language);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => ($download ? 'attachment' : 'inline').'; filename="'.$receipt->number.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
