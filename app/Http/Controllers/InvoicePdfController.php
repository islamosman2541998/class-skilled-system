<?php

namespace App\Http\Controllers;

use App\Actions\Finance\CreateInvoiceFromInstallment;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\ProjectInstallment;
use App\Services\ActivityLogger;
use App\Services\Pdf\PdfRenderer;
use App\Settings\SystemSettings;
use App\Support\WhatsApp;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * The tax invoice PDF, its WhatsApp link (spec §5.10), and the invoice made from an installment (§8.8).
 */
class InvoicePdfController extends Controller
{
    public function show(Request $request, Invoice $invoice, PdfRenderer $pdf): Response
    {
        Gate::authorize('invoices.print');
        $this->ensurePrintable($request, $invoice);

        $language = in_array($request->query('lang'), PdfRenderer::LANGUAGES, true) ? (string) $request->query('lang') : app()->getLocale();
        ActivityLogger::record('printed', $invoice, ['language' => $language], 'invoices');

        return self::pdfResponse($invoice, $pdf, $language, $request->boolean('download'));
    }

    /** A signed, expiring link to the PDF and a WhatsApp chat carrying it. */
    public function share(Request $request, Invoice $invoice, SystemSettings $system): JsonResponse
    {
        Gate::authorize('invoices.print');
        $this->ensurePrintable($request, $invoice);

        $data = $request->validate(['lang' => ['nullable', Rule::in(PdfRenderer::LANGUAGES)]]);
        $language = $data['lang'] ?? 'ar';
        $url = URL::temporarySignedRoute('public.invoice', now()->addHours($system->public_link_hours), ['invoice' => $invoice->id, 'lang' => $language]);

        $invoice->load('client');
        ActivityLogger::record('shared', $invoice, ['language' => $language], 'invoices');

        return response()->json([
            'url' => $url,
            'whatsapp' => WhatsApp::link($invoice->client->whatsapp ?: $invoice->client->phone, WhatsApp::invoiceMessage($invoice, $language === 'en' ? 'en' : 'ar', $url)),
        ]);
    }

    public function fromInstallment(Request $request, Project $project, ProjectInstallment $installment, CreateInvoiceFromInstallment $create): RedirectResponse
    {
        Gate::authorize('invoices.create');
        abort_unless($installment->project_id === $project->id && $project->isVisibleTo($request->user()), 404);

        try {
            $invoice = $create->handle($installment, $request->user());
        } catch (DomainException $exception) {
            return redirect()->route('projects.show', [$project, 'tab' => 'payments'])->with('error', $exception->getMessage());
        }

        return redirect()->route('invoices.show', $invoice);
    }

    public static function pdfResponse(Invoice $invoice, PdfRenderer $pdf, string $language, bool $download = false): Response
    {
        $invoice->loadMissing(['client', 'project', 'items.unit']);

        $bytes = $pdf->render('pdf.invoice', ['invoice' => $invoice], $language);
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$invoice->number.'.pdf"';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Drafts have no number and are not documents yet. */
    private function ensurePrintable(Request $request, Invoice $invoice): void
    {
        abort_unless($invoice->isVisibleTo($request->user()) && ! $invoice->isDraft(), 404);
    }
}
