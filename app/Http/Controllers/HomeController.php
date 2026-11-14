<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/**
 * Stage 0 home: the user's unread notifications and their own latest activity.
 * Role dashboards with figures replace this in Stage 3 (spec §9.1).
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('home', [
            'notifications' => $user->unreadNotifications()->latest()->limit(10)->get(),
            'activities' => Activity::query()
                ->where('causer_type', $user->getMorphClass())
                ->where('causer_id', $user->getKey())
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }
}
