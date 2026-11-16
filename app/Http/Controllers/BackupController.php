<?php

namespace App\Http\Controllers;

use App\Enums\BackupStatus;
use App\Enums\BackupType;
use App\Models\Backup;
use App\Services\Backup\BackupRunner;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The backups page (spec §13): last successful backup of each type, run now, download and delete.
 * Files never leave the private disk except through download(), for settings.manage only.
 */
class BackupController extends Controller
{
    public function index(): View
    {
        Gate::authorize('settings.manage');

        $last = fn (BackupType $type) => Backup::query()->where('type', $type->value)->where('status', BackupStatus::Success->value)->latest('started_at')->first();

        return view('settings.backups', [
            'backups' => Backup::query()->with('creator')->latest('started_at')->latest('id')->limit(50)->get(),
            'lastDatabase' => $last(BackupType::Database),
            'lastFiles' => $last(BackupType::Files),
        ]);
    }

    public function run(Request $request, string $type, BackupRunner $runner): RedirectResponse
    {
        Gate::authorize('settings.manage');
        $type = BackupType::tryFrom($type) ?? abort(404);

        try {
            $backup = $runner->run($type, $request->user());
        } catch (DomainException $exception) {
            return redirect()->route('settings.backups')->with('error', $exception->getMessage());
        }

        return $backup->status === BackupStatus::Success
            ? redirect()->route('settings.backups')->with('success', __('settings.backups.done'))
            : redirect()->route('settings.backups')->with('error', __('settings.backups.failed', ['error' => (string) $backup->error]));
    }

    public function download(Backup $backup): StreamedResponse
    {
        Gate::authorize('settings.manage');
        abort_unless($backup->hasSafePath() && Storage::disk('private')->exists($backup->path), 404);

        return Storage::disk('private')->download($backup->path, basename($backup->path));
    }

    public function destroy(Backup $backup): RedirectResponse
    {
        Gate::authorize('settings.manage');

        if ($backup->hasSafePath()) {
            Storage::disk('private')->delete($backup->path);
        }

        $backup->delete();

        return redirect()->route('settings.backups')->with('success', __('settings.backups.deleted'));
    }
}
