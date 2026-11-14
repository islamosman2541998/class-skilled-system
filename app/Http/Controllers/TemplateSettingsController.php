<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\TemplateSettingsRequest;
use App\Services\ActivityLogger;
use App\Settings\TemplateSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class TemplateSettingsController extends Controller
{
    public function edit(TemplateSettings $settings): View
    {
        Gate::authorize('settings.manage');

        return view('settings.templates', ['settings' => $settings]);
    }

    public function update(TemplateSettingsRequest $request, TemplateSettings $settings): RedirectResponse
    {
        $before = $settings->toArray();

        foreach ($request->values() as $key => $value) {
            $settings->{$key} = $value;
        }

        $settings->save();

        ActivityLogger::recordChange('settings_updated', $before, $settings->toArray(), null, ['group' => 'templates'], 'settings');

        return back()->with('success', __('app.saved'));
    }
}
