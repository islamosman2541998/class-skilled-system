<?php

namespace App\Http\Controllers;

use App\Enums\LookupType;
use App\Http\Requests\Settings\AppearanceSettingsRequest;
use App\Http\Requests\Settings\CompanySettingsRequest;
use App\Http\Requests\Settings\FinanceSettingsRequest;
use App\Http\Requests\Settings\SettingsRequest;
use App\Http\Requests\Settings\SystemSettingsRequest;
use App\Http\Requests\Settings\UploadSettingsRequest;
use App\Services\ActivityLogger;
use App\Settings\AppearanceSettings;
use App\Settings\CompanySettings;
use App\Settings\FinanceSettings;
use App\Settings\SystemSettings;
use App\Settings\UploadSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Spatie\LaravelSettings\Settings;
use Throwable;

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
        $before = $settings->toArray();

        foreach ($request->values() as $key => $value) {
            $settings->{$key} = $value;
        }

        [$oldImages, $newImages] = $settings instanceof CompanySettings
            ? $this->storeBrandingImages($request, $settings)
            : [[], []];

        try {
            $settings->save();
        } catch (Throwable $e) {
            foreach ($newImages as $path) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }

        foreach ($oldImages as $path) {
            Storage::disk('public')->delete($path);
        }

        ActivityLogger::recordChange('settings_updated', $before, $settings->toArray(), null, ['group' => $group], 'settings');

        return redirect()->route('settings.edit', $group)->with('success', __('app.saved'));
    }

    private function settings(string $group): Settings
    {
        return app(self::GROUPS[$group][0]);
    }

    /** @return array{list<string>, list<string>} */
    private function storeBrandingImages(SettingsRequest $request, CompanySettings $settings): array
    {
        $disk = Storage::disk('public');
        $oldImages = [];
        $newImages = [];

        foreach (CompanySettingsRequest::IMAGES as $field => $property) {
            $current = $settings->{$property};

            if ($request->hasFile($field)) {
                try {
                    $path = $request->file($field)->store('branding', 'public');
                } catch (Throwable $e) {
                    report($e);
                    $path = false;
                }

                if ($path === false) {
                    foreach ($newImages as $newPath) {
                        $disk->delete($newPath);
                    }

                    throw ValidationException::withMessages([$field => __('settings.company.upload_failed')]);
                }

                $settings->{$property} = $path;
                $newImages[] = $path;
            } elseif ($request->boolean('remove_'.$field)) {
                $settings->{$property} = null;
            } else {
                continue;
            }

            if ($current) {
                $oldImages[] = $current;
            }
        }

        return [$oldImages, $newImages];
    }
}
