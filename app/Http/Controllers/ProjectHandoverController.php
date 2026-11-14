<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Enums\ProjectStatus;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Project;
use App\Models\ProjectHandover;
use App\Services\ActivityLogger;
use App\Services\Pdf\PdfRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Handover and warranty of a project, and the handover record PDF (spec §8.18, §5.10).
 */
class ProjectHandoverController extends Controller
{
    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('projects.update');
        $this->ensureVisible($request, $project);

        $data = $request->validate([
            'handover_date' => ['required', 'date'],
            'warranty_months' => ['required', 'integer', 'min:0', 'max:240'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($project, $data, $request) {
            $date = Carbon::parse($data['handover_date']);

            $project->handover()->updateOrCreate([], [
                'handover_date' => $date,
                'warranty_months' => (int) $data['warranty_months'],
                'warranty_end' => ProjectHandover::warrantyEnd($date, (int) $data['warranty_months']),
                'notes' => $data['notes'] ?? null,
                'created_by' => $project->handover->created_by ?? $request->user()->id,
            ]);

            $project->status = ProjectStatus::Delivered;
            $project->actual_end_date ??= $date;
            $project->save();
        });

        return redirect()->route('projects.show', [$project, 'tab' => 'handover'])->with('success', __('projects.handover.saved'));
    }

    public function pdf(Request $request, Project $project, PdfRenderer $pdf): Response
    {
        Gate::authorize('projects.print');
        $this->ensureVisible($request, $project);

        $handover = $project->handover;
        abort_if($handover === null, 404);

        $language = in_array($request->query('lang'), PdfRenderer::LANGUAGES, true) ? (string) $request->query('lang') : app()->getLocale();
        ActivityLogger::record('printed', $project, ['document' => 'handover', 'language' => $language], 'projects');

        $bytes = $pdf->render('pdf.handover', ['project' => $project->load(['client', 'stages', 'manager']), 'handover' => $handover], $language);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$project->number.'-handover.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function attach(StoreAttachmentsRequest $request, Project $project, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('projects.update');
        $this->ensureVisible($request, $project);
        abort_if($project->handover === null, 404);

        $attach->handle($project->handover, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    private function ensureVisible(Request $request, Project $project): void
    {
        abort_unless($project->isVisibleTo($request->user()), 404);
    }
}
