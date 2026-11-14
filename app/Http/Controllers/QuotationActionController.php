<?php

namespace App\Http\Controllers;

use App\Actions\Quotations\ChangeQuotationStatus;
use App\Actions\Quotations\CopyQuotation;
use App\Actions\Quotations\ReviseQuotation;
use App\Enums\QuotationStatus;
use App\Http\Requests\RejectQuotationRequest;
use App\Models\Quotation;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Quotation workflow (spec §8.5). Order of checks: permission, visibility, lock.
 */
class QuotationActionController extends Controller
{
    public function __construct(private readonly ChangeQuotationStatus $status) {}

    public function send(Request $request, Quotation $quotation): RedirectResponse
    {
        return $this->guarded($request, $quotation, 'quotations.update', function () use ($request, $quotation) {
            if ($quotation->status !== QuotationStatus::Draft) {
                return back()->with('error', __('quotations.not_draft'));
            }

            $this->status->send($quotation, $request->user());

            return back()->with('success', __('quotations.marked_sent'));
        });
    }

    public function accept(Request $request, Quotation $quotation): RedirectResponse
    {
        return $this->guarded($request, $quotation, 'quotations.approve', function () use ($request, $quotation) {
            $this->status->accept($quotation, $request->user());

            return back()->with('success', __('quotations.accepted'));
        });
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        return $this->guarded($request, $quotation, 'quotations.update', function () use ($request, $quotation) {
            $data = app(RejectQuotationRequest::class)->validated();
            $this->status->reject($quotation, (int) $data['rejection_reason_id'], $data['rejection_note'] ?? null, $request->user());

            return back()->with('success', __('quotations.rejected'));
        });
    }

    public function revise(Request $request, Quotation $quotation, ReviseQuotation $revise): RedirectResponse
    {
        return $this->guarded($request, $quotation, 'quotations.update', function () use ($request, $quotation, $revise) {
            $revision = $revise->handle($quotation, $request->user());

            return redirect()->route('quotations.edit', $revision)->with('success', __('quotations.revised', ['number' => $revision->number]));
        });
    }

    /** Copy works on any quotation, locked or not. */
    public function copy(Request $request, Quotation $quotation, CopyQuotation $copy): RedirectResponse
    {
        Gate::authorize('quotations.create');
        abort_unless($quotation->isVisibleTo($request->user()), 404);

        $new = $copy->handle($quotation, $request->user());

        return redirect()->route('quotations.edit', $new)->with('success', __('quotations.copied', ['number' => $new->number]));
    }

    /**
     * @param  Closure(): RedirectResponse  $action
     */
    private function guarded(Request $request, Quotation $quotation, string $permission, Closure $action): RedirectResponse
    {
        Gate::authorize($permission);
        abort_unless($quotation->isVisibleTo($request->user()), 404);

        if ($quotation->isLocked()) {
            return redirect()->route('quotations.show', $quotation)->with('error', __('quotations.locked'));
        }

        return $action();
    }
}
