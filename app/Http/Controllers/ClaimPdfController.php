<?php

namespace App\Http\Controllers;

use App\Models\Claim;
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
 * The progress claim PDF and its WhatsApp link (spec §5.10, §8.14). Drafts are not documents yet.
 */
class ClaimPdfController extends Controller
{
    public function show(Request $request, Claim $claim, PdfRenderer $pdf): Response
    {
        Gate::authorize('claims.print');
        $this->ensurePrintable($request, $claim);

        $language = in_array($request->query('lang'), PdfRenderer::LANGUAGES, true) ? (string) $request->query('lang') : app()->getLocale();
        ActivityLogger::record('printed', $claim, ['language' => $language], 'claims');

        return self::pdfResponse($claim, $pdf, $language, $request->boolean('download'));
    }

    /** A signed, expiring link to the PDF and a WhatsApp chat carrying it. */
    public function share(Request $request, Claim $claim, SystemSettings $system): JsonResponse
    {
        Gate::authorize('claims.print');
        $this->ensurePrintable($request, $claim);

        $data = $request->validate(['lang' => ['nullable', Rule::in(PdfRenderer::LANGUAGES)]]);
        $language = $data['lang'] ?? 'ar';
        $url = URL::temporarySignedRoute('public.claim', now()->addHours($system->public_link_hours), ['claim' => $claim->id, 'lang' => $language]);

        $claim->load('client');
        ActivityLogger::record('shared', $claim, ['language' => $language], 'claims');

        return response()->json([
            'url' => $url,
            'whatsapp' => WhatsApp::link($claim->client->whatsapp ?: $claim->client->phone, WhatsApp::claimMessage($claim, $language === 'en' ? 'en' : 'ar', $url)),
        ]);
    }

    public static function pdfResponse(Claim $claim, PdfRenderer $pdf, string $language, bool $download = false): Response
    {
        $claim->loadMissing(['client', 'project', 'lines.unit']);

        $bytes = $pdf->render('pdf.claim', ['claim' => $claim], $language);
        $disposition = ($download ? 'attachment' : 'inline').'; filename="'.$claim->number.'.pdf"';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function ensurePrintable(Request $request, Claim $claim): void
    {
        abort_unless($claim->isVisibleTo($request->user()) && ! $claim->isDraft(), 404);
    }
}
