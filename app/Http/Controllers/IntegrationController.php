<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\WebsiteSync;
use App\Settings\IntegrationSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Website link settings and the "fetch requests" button (spec §7, §10).
 */
class IntegrationController extends Controller
{
    public function edit(IntegrationSettings $settings): View
    {
        Gate::authorize('settings.manage');

        return view('settings.integration', ['settings' => $settings]);
    }

    public function update(Request $request, IntegrationSettings $settings): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $data = $request->validate(['website_url' => ['nullable', 'url:https', 'max:190']]);
        $settings->website_url = $data['website_url'] ? rtrim($data['website_url'], '/') : null;
        $settings->save();

        ActivityLogger::record('settings_updated', null, ['group' => 'integration'], 'settings');

        return back()->with('success', __('app.saved'));
    }

    /** A new shared key, shown once; only its hash checks incoming calls. */
    public function generateKey(IntegrationSettings $settings): RedirectResponse
    {
        Gate::authorize('settings.manage');

        $key = Str::random(48);
        $settings->api_key_hash = hash('sha256', $key);
        $settings->outgoing_key = $key;
        $settings->save();

        ActivityLogger::record('settings_updated', null, ['group' => 'integration', 'key' => 'regenerated'], 'settings');

        return back()->with('generated_key', $key)->with('success', __('settings.integration.key_generated'));
    }

    public function fetch(WebsiteSync $sync, IntegrationSettings $settings): RedirectResponse
    {
        Gate::authorize('leads.create');

        $count = $sync->fetch();

        return $settings->last_sync_status === 'ok'
            ? back()->with('success', __('leads.website.fetched', ['count' => $count]))
            : back()->with('error', __('leads.website.fetch_failed'));
    }
}
