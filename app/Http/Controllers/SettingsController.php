<?php

namespace App\Http\Controllers;

use App\Enums\LookupType;
use App\Http\Requests\Settings\AppearanceSettingsRequest;
use App\Http\Requests\Settings\CompanySettingsRequest;
use App\Http\Requests\Settings\FinanceSettingsRequest;
use App\Http\Requests\Settings\SettingsRequest;
use App\Http\Requests\Settings\SystemSettingsRequest;
use App\Http\Requests\Settings\UploadSettingsRequest;
use App\Settings\AppearanceSettings;
use App\Settings\CompanySettings;
use App\Settings\FinanceSettings;
use App\Settings\SystemSettings;
use App\Settings\UploadSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Spatie\LaravelSettings\Settings;

class SettingsController extends Controller
{
    /** group => [settings class, request class] */
    public const GROUPS = [
        'company' => [CompanySettings::class, CompanySettingsRequest::class],
        'appearance' => [AppearanceSettings::class, AppearanceSettingsRequest::class],
        'finance' => [FinanceSettings::class, FinanceSettingsRequest::class],
        'uploads' => [UploadSettings::class, UploadSettingsRequest::class],
        'system' => [SystemSettings::class, SystemSettingsRequest::class],
    ];

    public function index(): View
    {
        Gate::authorize('settings.manage');

        return view('settings.index', [
            'groups' => array_keys(self::GROUPS),
            'lists' => LookupType::cases(),
        ]);
    }

    public function edit(string $group): View
    {
        Gate::authorize('settings.manage');

        return view("settings.{$group}", ['settings' => $this->settings($group), 'group' => $group]);
    }

    public function update(string $group): RedirectResponse
    {
        /** @var SettingsRequest $request */
        $request = app(self::GROUPS[$group][1]);
        $settings = $this->settings($group);

        foreach ($request->values() as $key => $value) {
            $settings->{$key} = $value;
        }

        if ($settings instanceof CompanySettings) {
            $this->storeBrandingImages($request, $settings);
        }

        $settings->save();

        return redirect()->route('settings.edit', $group)->with('success', __('app.saved'));
    }

    private function settings(string $group): Settings
    {
        return app(self::GROUPS[$group][0]);
    }

    private function storeBrandingImages(SettingsRequest $request, CompanySettings $settings): void
    {
        $disk = Storage::disk('public');

        foreach (CompanySettingsRequest::IMAGES as $field => $property) {
            $current = $settings->{$property};

            if ($request->hasFile($field)) {
                $settings->{$property} = $request->file($field)->store('branding', 'public');
            } elseif ($request->boolean('remove_'.$field)) {
                $settings->{$property} = null;
            } else {
                continue;
            }

            if ($current && $disk->exists($current)) {
                $disk->delete($current);
            }
        }
    }
}
