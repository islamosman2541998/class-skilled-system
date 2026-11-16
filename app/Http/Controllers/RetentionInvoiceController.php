<?php

namespace App\Http\Controllers;

use App\Actions\Site\CreateRetentionInvoice;
use App\Models\Project;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** "Retention invoice" on the claims tab after handover (spec §5.9). */
class RetentionInvoiceController extends Controller
{
    public function store(Request $request, Project $project, CreateRetentionInvoice $create): RedirectResponse
    {
        Gate::authorize('invoices.create');
        abort_unless($project->isVisibleTo($request->user()), 404);

        try {
            $invoice = $create->handle($project, $request->user());
        } catch (DomainException $exception) {
            return redirect()->route('projects.show', [$project, 'tab' => 'claims'])->with('error', $exception->getMessage());
        }

        return redirect()->route('invoices.show', $invoice)->with('success', __('site.retention.created'));
    }
}
