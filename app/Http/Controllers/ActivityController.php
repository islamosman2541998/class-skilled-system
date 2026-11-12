<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ActivityController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('activity.view');

        return view('activity.index');
    }
}
