<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Actions\Projects\SaveProject;
use App\Enums\BillingMethod;
use App\Enums\LookupType;
use App\Http\Requests\SaveProjectRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\Client;
use App\Models\Lookup;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View as ViewFactory;
use Illuminate\View\View;

class ProjectController extends Controller
{
    /** Tabs of the project page; a tab appears once its view exists (spec §8.6, built stage by stage). */
    public const TABS = ['overview', 'stages', 'payments', 'variations', 'attachments', 'handover', 'activity'];

    /**
     * Header buttons added by later tasks and stages, each receiving $project.
     *
     * @var list<string>
     */
    public static array $actionViews = [];

    public function index(): View
    {
        Gate::authorize('projects.view');

        return view('projects.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('projects.create');

        return $this->form(new Project([
            'client_id' => $request->integer('client') ?: null,
            'billing_method' => BillingMethod::Installments,
            'manager_id' => $request->user()->id,
            'start_date' => today(),
        ]));
    }

    public function store(SaveProjectRequest $request, SaveProject $save): RedirectResponse
    {
        $project = $save->handle($request->validated(), $request->user());

        return redirect()->route('projects.show', $project)->with('success', __('app.saved'));
    }

    public function show(Request $request, Project $project): View
    {
        Gate::authorize('projects.view');
        $this->ensureVisible($request, $project);

        $tabs = collect(self::TABS)
            ->filter(fn (string $tab) => in_array($tab, ['overview', 'attachments', 'activity'], true) || ViewFactory::exists('projects.tabs.'.$tab))
            ->mapWithKeys(fn (string $tab) => [$tab => __('projects.tabs.'.$tab)])
            ->all();
        $active = array_key_exists((string) $request->query('tab'), $tabs) ? (string) $request->query('tab') : 'overview';

        $project->load(['client', 'service', 'emirate', 'manager', 'members', 'creator', 'quotation']);

        return view('projects.show', ['project' => $project, 'tabs' => $tabs, 'active' => $active]);
    }

    public function edit(Request $request, Project $project): View
    {
        Gate::authorize('projects.update');
        $this->ensureVisible($request, $project);

        return $this->form($project->load('members'));
    }

    public function update(SaveProjectRequest $request, Project $project, SaveProject $save): RedirectResponse
    {
        $this->ensureVisible($request, $project);

        // The client and the original value come from the contract and do not change here.
        $save->handle($request->safe()->except(['client_id', 'original_value']), $request->user(), $project);

        return redirect()->route('projects.show', $project)->with('success', __('app.saved'));
    }

    public function attach(StoreAttachmentsRequest $request, Project $project, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('projects.update');
        $this->ensureVisible($request, $project);

        $attach->handle($project, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    private function form(Project $project): View
    {
        return view('projects.form', [
            'project' => $project,
            'clients' => $project->client_id ? Client::query()->whereKey($project->client_id)->get() : collect(),
            'services' => Lookup::options(LookupType::Service),
            'emirates' => Lookup::options(LookupType::Emirate),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'memberIds' => old('members', $project->exists ? $project->members->pluck('id')->all() : []),
        ]);
    }

    private function ensureVisible(Request $request, Project $project): void
    {
        abort_unless($project->isVisibleTo($request->user()), 404);
    }
}
