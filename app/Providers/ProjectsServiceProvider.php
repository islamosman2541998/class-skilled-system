<?php

namespace App\Providers;

use App\Enums\LookupType;
use App\Models\Lookup;
use App\Models\StageTemplate;
use App\Support\LookupUsage;
use App\Support\PermissionRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Stage 2 (projects): the one place where project modules join the Stage 0 registries.
 */
class ProjectsServiceProvider extends ServiceProvider
{
    /** Static registries live for the whole PHP process; they are filled once, not on every boot. */
    private static bool $registered = false;

    public function boot(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        PermissionRegistry::register('projects', [...PermissionRegistry::ACTIONS, 'approve']);
        PermissionRegistry::register('maintenance', ['view', 'create', 'update', 'delete', 'restore', 'force-delete', 'export']);

        LookupUsage::register(LookupType::Service, fn (Lookup $lookup) => StageTemplate::withTrashed()->where('service_id', $lookup->id)->exists());
    }
}
