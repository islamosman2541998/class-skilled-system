<?php

namespace App\Http\Controllers;

use App\Support\ReportRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * The reports list and one report page (spec §9.2).
 */
class ReportController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('reports.view');

        return view('reports.index', ['groups' => ReportRegistry::forUser($request->user())]);
    }

    public function show(Request $request, string $report): View
    {
        Gate::authorize('reports.view');
        abort_if(ReportRegistry::find($report) === null, 404);
        abort_unless(ReportRegistry::allows($request->user(), $report), 403);

        return view('reports.show', ['key' => $report, 'report' => ReportRegistry::find($report)]);
    }
}
