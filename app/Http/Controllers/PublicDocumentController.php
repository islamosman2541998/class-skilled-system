<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use App\Services\Pdf\PdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Documents opened by clients from a signed, expiring link (spec §5.10, §12). The "signed"
 * middleware rejects altered or expired links before this runs.
 */
class PublicDocumentController extends Controller
{
    public function quotation(Request $request, Quotation $quotation, PdfRenderer $pdf): Response
    {
        $language = (string) $request->query('lang', 'ar');

        return QuotationPdfController::pdfResponse($quotation, $pdf, in_array($language, PdfRenderer::LANGUAGES, true) ? $language : 'ar');
    }
}
