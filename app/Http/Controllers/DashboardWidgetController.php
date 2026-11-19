<?php

namespace App\Http\Controllers;

use App\Models\DashboardPreference;
use App\Services\Dashboard\Widgets;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardWidgetController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visible_widgets' => ['sometimes', 'array'],
            'visible_widgets.*' => ['string', Rule::in(Widgets::keys())],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $user = $request->user();
        $available = array_keys(Widgets::available($user));
        $visible = array_values(array_intersect($available, $validated['visible_widgets'] ?? []));
        $preference = DashboardPreference::query()->firstOrNew(['user_id' => $user->id]);
        $unavailableHidden = array_intersect(
            $preference->hidden_widgets ?? [],
            array_diff(Widgets::keys(), $available),
        );

        $preference->hidden_widgets = array_values(array_unique([
            ...$unavailableHidden,
            ...array_diff($available, $visible),
        ]));
        $preference->save();

        return redirect()->route('home', array_filter([
            'from' => $validated['from'] ?? null,
            'to' => $validated['to'] ?? null,
        ]))->with('success', __('home.widgets_saved'));
    }
}
