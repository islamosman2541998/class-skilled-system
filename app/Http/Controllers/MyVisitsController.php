<?php

namespace App\Http\Controllers;

use App\Enums\SiteVisitStatus;
use App\Models\SiteVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * "My visits": the signed-in engineer's scheduled visits from today on, as cards (spec §8.3).
 */
class MyVisitsController extends Controller
{
    public function __invoke(Request $request): View
    {
        Gate::authorize('site_visits.view');

        $visits = SiteVisit::query()
            ->with(['client', 'lead.service'])
            ->where('engineer_id', $request->user()->id)
            ->where('status', SiteVisitStatus::Scheduled)
            ->where('scheduled_at', '>=', today())
            ->orderBy('scheduled_at')
            ->limit(100)
            ->get();

        return view('site-visits.my', [
            'today' => $visits->filter(fn (SiteVisit $visit) => $visit->scheduled_at->isToday()),
            'upcoming' => $visits->reject(fn (SiteVisit $visit) => $visit->scheduled_at->isToday()),
        ]);
    }
}
