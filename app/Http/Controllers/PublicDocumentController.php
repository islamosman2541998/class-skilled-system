<?php

namespace App\Http\Controllers;

use App\Models\Claim;
use App\Models\Invoice;
use App\Models\Quotation;
use App\Models\Receipt;
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

    public function receipt(Request $request, Receipt $receipt, PdfRenderer $pdf): Response
    {
        $language = (string) $request->query('lang', 'ar');

        return ReceiptPdfController::pdfResponse($receipt, $pdf, in_array($language, PdfRenderer::LANGUAGES, true) ? $language : 'ar');
    }

    public function claim(Request $request, Claim $claim, PdfRenderer $pdf): Response
    {
        abort_if($claim->isDraft(), 404);
        $language = (string) $request->query('lang', 'ar');

        return ClaimPdfController::pdfResponse($claim, $pdf, in_array($language, PdfRenderer::LANGUAGES, true) ? $language : 'ar');
    }

    public function invoice(Request $request, Invoice $invoice, PdfRenderer $pdf): Response
    {
        abort_if($invoice->isDraft(), 404);
        $language = (string) $request->query('lang', 'ar');

        return InvoicePdfController::pdfResponse($invoice, $pdf, in_array($language, PdfRenderer::LANGUAGES, true) ? $language : 'ar');
    }
}
