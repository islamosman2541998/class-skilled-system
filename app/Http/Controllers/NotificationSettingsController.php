<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Http\Requests\Settings\NotificationSettingsRequest;
use App\Models\Role;
use App\Services\ActivityLogger;
use App\Settings\NotificationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NotificationSettingsController extends Controller
{
    public function edit(NotificationSettings $settings): View
    {
        Gate::authorize('settings.manage');

        return view('settings.notifications', [
            'types' => NotificationType::cases(),
            'settings' => $settings,
            'roles' => Role::query()->orderBy('id')->get(),
        ]);
    }

    public function update(NotificationSettingsRequest $request, NotificationSettings $settings): RedirectResponse
    {
        $before = $settings->rules;
        $settings->rules = array_merge($settings->rules, $request->values());
        $settings->save();

        ActivityLogger::record('settings_updated', null, ['group' => 'notifications', 'old' => $before, 'attributes' => $settings->rules], 'settings');

        return back()->with('success', __('app.saved'));
    }
}
