<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Services\ActivityLogger;
use App\Services\Pdf\PdfRenderer;
use App\Settings\SystemSettings;
use App\Support\WhatsApp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Quotation PDF in Arabic, English or both, and the WhatsApp share link (spec §5.10).
 */
class QuotationPdfController extends Controller
{
    public function show(Request $request, Quotation $quotation, PdfRenderer $pdf): Response
    {
        Gate::authorize('quotations.print');
        abort_unless($quotation->isVisibleTo($request->user()), 404);

        $language = $this->language($request);
        ActivityLogger::record('printed', $quotation, ['language' => $language], 'quotations');

        return self::pdfResponse($quotation, $pdf, $language, $request->boolean('download'));
    }

    /** A signed, expiring link to the PDF and a WhatsApp chat carrying it. */
    public function share(Request $request, Quotation $quotation, SystemSettings $system): JsonResponse
    {
        Gate::authorize('quotations.print');
        abort_unless($quotation->isVisibleTo($request->user()), 404);

        $data = $request->validate(['lang' => ['nullable', Rule::in(PdfRenderer::LANGUAGES)]]);
        $language = $data['lang'] ?? 'ar';
        $url = URL::temporarySignedRoute('public.quotation', now()->addHours($system->public_link_hours), ['quotation' => $quotation->id, 'lang' => $language]);

        $quotation->load('client');
        $message = WhatsApp::quotationMessage($quotation, $language === 'en' ? 'en' : 'ar', $url);

        ActivityLogger::record('shared', $quotation, ['language' => $language], 'quotations');

        return response()->json([
            'url' => $url,
            'whatsapp' => WhatsApp::link($quotation->client->whatsapp ?: $quotation->client->phone, $message),
        ]);
    }

    public static function pdfResponse(Quotation $quotation, PdfRenderer $pdf, string $language, bool $download = false): Response
    {
        $quotation->loadMissing(['client', 'service', 'emirate', 'items.unit', 'payments', 'assignee']);

        $bytes = $pdf->render('pdf.quotation', ['quotation' => $quotation], $language);
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$quotation->number.'.pdf"';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function language(Request $request): string
    {
        $language = (string) $request->query('lang', app()->getLocale());

        return in_array($language, PdfRenderer::LANGUAGES, true) ? $language : 'ar';
    }
}
