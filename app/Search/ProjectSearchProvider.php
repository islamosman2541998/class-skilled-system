<?php

namespace App\Search;

use App\Models\Project;
use App\Models\User;
use App\Support\Like;
use Illuminate\Support\Collection;

class ProjectSearchProvider implements SearchProvider
{
    public function key(): string
    {
        return 'projects';
    }

    public function labelKey(): string
    {
        return 'projects.projects';
    }

    public function search(string $term, User $user, int $limit = 5): Collection
    {
        if (! $user->can('projects.view')) {
            return collect();
        }

        return Like::any(Project::query()->with('client')->visibleTo($user), ['number', 'title_ar', 'title_en', 'client.name_ar', 'client.name_en'], $term)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Project $project) => new SearchResult($project->number.' · '.$project->title, $project->client->name, route('projects.show', $project)));
    }
}
