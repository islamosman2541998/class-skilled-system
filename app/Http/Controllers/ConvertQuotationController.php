<?php

namespace App\Http\Controllers;

use App\Actions\Projects\CreateProjectFromQuotation;
use App\Enums\BillingMethod;
use App\Enums\QuotationStatus;
use App\Http\Requests\ConvertQuotationRequest;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use LogicException;

/**
 * "Accept and convert to project" (spec §8.5): a short form confirms dates, manager and team; the
 * rest comes from the quotation.
 */
class ConvertQuotationController extends Controller
{
    public function create(Request $request, Quotation $quotation): View|RedirectResponse
    {
        Gate::authorize('projects.create');
        abort_unless($quotation->isVisibleTo($request->user()), 404);

        if ($quotation->project()->exists()) {
            return redirect()->route('projects.show', $quotation->project);
        }

        if ($quotation->status !== QuotationStatus::Accepted) {
            return redirect()->route('quotations.show', $quotation)->with('error', __('projects.convert_not_accepted'));
        }

        return view('projects.convert', [
            'quotation' => $quotation->load(['client', 'service', 'items', 'payments']),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'billingMethods' => BillingMethod::cases(),
        ]);
    }

    public function store(ConvertQuotationRequest $request, Quotation $quotation, CreateProjectFromQuotation $convert): RedirectResponse
    {
        abort_unless($quotation->isVisibleTo($request->user()), 404);

        try {
            $project = $convert->handle($quotation, $request->validated(), $request->user());
        } catch (LogicException) {
            return redirect()->route('quotations.show', $quotation)->with('error', __('projects.convert_not_accepted'));
        }

        return redirect()->route('projects.show', $project)->with('success', __('projects.converted', ['number' => $project->number]));
    }
}
