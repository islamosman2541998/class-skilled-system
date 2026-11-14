<?php

namespace App\Search;

use App\Models\MaintenanceRequest;
use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MaintenanceSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'maintenance';
    }

    public function labelKey(): string
    {
        return 'maintenance.maintenance';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('maintenance.view')) {
            return collect();
        }

        return Like::any(MaintenanceRequest::query()->with('project')->visibleTo($user), ['number', 'description', 'project.title_ar', 'project.title_en'], $term)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (MaintenanceRequest $request) => new SearchResult($request->number.' · '.$request->project->title, Str::limit($request->description, 60), route('maintenance.show', $request)));
    }
}
