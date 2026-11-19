<?php

namespace App\Http\Controllers;

use App\Models\DashboardPreference;
use App\Services\Dashboard\DashboardRegistry;
use App\Services\Dashboard\Period;
use App\Services\Dashboard\Widgets;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

/**
 * The home dashboard (spec §9.1): the figures the user's permissions allow for the chosen period,
 * revenue against cost for those who see profitability, then notifications and own activity.
 */
class HomeController extends Controller
{
    /** Panels in this order; groups not listed follow. */
    private const GROUP_ORDER = ['finance', 'projects', 'sales'];

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $period = Period::fromRequest($request);

        $figures = DashboardRegistry::for($user, $period);
        uksort($figures, fn (string $a, string $b) => self::rank($a) <=> self::rank($b));
        $chartOptions = Widgets::available($user);
        $hiddenCharts = DashboardPreference::query()
            ->where('user_id', $user->id)
            ->first()?->hidden_widgets ?? [];
        $charts = Widgets::for($user, $period, $hiddenCharts);

        return view('home', [
            'period' => $period,
            'figures' => $figures,
            'chartOptions' => $chartOptions,
            'hiddenCharts' => $hiddenCharts,
            'charts' => $charts,
            'chart' => $charts['revenue_cost']['data'] ?? null,
            'notifications' => $user->unreadNotifications()->latest()->limit(10)->get(),
            'activities' => Activity::query()
                ->where('causer_type', $user->getMorphClass())
                ->where('causer_id', $user->getKey())
                ->latest('id')
                ->limit(10)
                ->get(),
        ]);
    }

    private static function rank(string $group): int
    {
        $index = array_search($group, self::GROUP_ORDER, true);

        return $index === false ? count(self::GROUP_ORDER) : $index;
    }
}
