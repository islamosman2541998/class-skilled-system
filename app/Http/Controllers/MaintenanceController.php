<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Enums\DocumentType;
use App\Enums\MaintenanceStatus;
use App\Enums\NotificationType;
use App\Http\Requests\SaveMaintenanceRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Models\MaintenanceRequest;
use App\Models\Project;
use App\Models\User;
use App\Services\Notifier;
use App\Services\NumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Maintenance requests (spec §8.18).
 */
class MaintenanceController extends Controller
{
    public function __construct(private readonly Notifier $notifier) {}

    public function index(): View
    {
        Gate::authorize('maintenance.view');

        return view('maintenance.index');
    }

    public function create(Request $request): View
    {
        Gate::authorize('maintenance.create');

        $project = $request->integer('project') ? Project::query()->visibleTo($request->user())->find($request->integer('project')) : null;

        return $this->form($request, new MaintenanceRequest(['project_id' => $project?->id, 'request_date' => today()]));
    }

    public function store(SaveMaintenanceRequest $request, NumberGenerator $numbers): RedirectResponse
    {
        $maintenance = DB::transaction(function () use ($request, $numbers) {
            $maintenance = new MaintenanceRequest($request->safe()->except(['in_warranty', 'status', 'cost']));
            $maintenance->number = $numbers->next(DocumentType::Maintenance);
            $maintenance->in_warranty = $request->computedWarranty();
            $maintenance->status = MaintenanceStatus::Open;
            $maintenance->cost = (string) ($request->validated('cost') ?? 0);
            $maintenance->created_by = $request->user()->id;
            $maintenance->save();

            return $maintenance;
        });

        $this->notifyAssignee($maintenance, $request->user());

        return redirect()->route('maintenance.show', $maintenance)->with('success', __('app.saved'));
    }

    public function show(Request $request, MaintenanceRequest $maintenance): View
    {
        Gate::authorize('maintenance.view');
        $this->ensureVisible($request, $maintenance);

        return view('maintenance.show', ['maintenance' => $maintenance->load(['project.client', 'project.handover', 'assignee'])]);
    }

    public function edit(Request $request, MaintenanceRequest $maintenance): View
    {
        Gate::authorize('maintenance.update');
        $this->ensureVisible($request, $maintenance);

        return $this->form($request, $maintenance);
    }

    public function update(SaveMaintenanceRequest $request, MaintenanceRequest $maintenance): RedirectResponse
    {
        $this->ensureVisible($request, $maintenance);

        $previous = $maintenance->assigned_to;
        $maintenance->fill($request->safe()->except(['cost']));
        $maintenance->cost = (string) ($request->validated('cost') ?? 0);
        $maintenance->save();

        if ($maintenance->assigned_to !== null && $maintenance->assigned_to !== $previous) {
            $this->notifyAssignee($maintenance, $request->user());
        }

        return redirect()->route('maintenance.show', $maintenance)->with('success', __('app.saved'));
    }

    public function attach(StoreAttachmentsRequest $request, MaintenanceRequest $maintenance, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('maintenance.update');
        $this->ensureVisible($request, $maintenance);

        $attach->handle($maintenance, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    private function notifyAssignee(MaintenanceRequest $maintenance, User $by): void
    {
        $assignee = $maintenance->assigned_to ? User::query()->find($maintenance->assigned_to) : null;

        if ($assignee !== null && ! $assignee->is($by)) {
            $this->notifier->toUser($assignee, NotificationType::Assigned, $maintenance, ['number' => $maintenance->number], route('maintenance.show', $maintenance));
        }
    }

    private function form(Request $request, MaintenanceRequest $maintenance): View
    {
        return view('maintenance.form', [
            'maintenance' => $maintenance,
            'projects' => Project::query()->visibleTo($request->user())->latest('id')->get(['id', 'number', 'title_ar', 'title_en']),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function ensureVisible(Request $request, MaintenanceRequest $maintenance): void
    {
        abort_unless($maintenance->isVisibleTo($request->user()), 404);
    }
}
