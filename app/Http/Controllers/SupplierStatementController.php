<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\ActivityLogger;
use App\Services\Pdf\PdfRenderer;
use App\Services\Resources\SupplierStatement;
use App\Support\ReportRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * The supplier statement of account as a PDF in Arabic, English or both (spec §9.2). Same lines and
 * same permissions as the statement report.
 */
class SupplierStatementController extends Controller
{
    public function pdf(Request $request, Supplier $supplier, PdfRenderer $pdf): Response
    {
        abort_unless(ReportRegistry::allows($request->user(), 'supplier-statement'), 403);

        $data = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'lang' => ['nullable', Rule::in(PdfRenderer::LANGUAGES)],
        ]);
        $from = isset($data['from']) ? Carbon::parse($data['from']) : null;
        $to = isset($data['to']) ? Carbon::parse($data['to']) : null;
        $language = $data['lang'] ?? app()->getLocale();

        $lines = SupplierStatement::query($supplier->id, $request->user(), $from, $to)->orderBy('id')->get();
        ActivityLogger::record('printed', $supplier, ['document' => 'statement', 'language' => $language], 'suppliers');

        $bytes = $pdf->render('pdf.supplier-statement', [
            'supplier' => $supplier,
            'lines' => $lines,
            'from' => $from,
            'to' => $to,
            'debit' => $lines->reduce(fn (string $sum, object $line) => bcadd($sum, (string) ($line->debit ?? '0'), 2), '0'),
            'credit' => $lines->reduce(fn (string $sum, object $line) => bcadd($sum, (string) ($line->credit ?? '0'), 2), '0'),
            'closing' => $lines->isEmpty() ? '0.00' : bcadd((string) $lines->last()->balance, '0', 2),
        ], $language);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="supplier-statement-'.$supplier->id.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
