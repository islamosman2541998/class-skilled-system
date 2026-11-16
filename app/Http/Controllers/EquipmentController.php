<?php

namespace App\Http\Controllers;

use App\Enums\EquipmentOwnership;
use App\Enums\LookupType;
use App\Http\Requests\SaveEquipmentRequest;
use App\Models\Equipment;
use App\Models\Lookup;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Equipment and its assignments history (spec §8.17). */
class EquipmentController extends Controller
{
    public function index(): View
    {
        Gate::authorize('equipment.view');

        return view('equipment.index');
    }

    public function create(): View
    {
        Gate::authorize('equipment.create');

        return $this->form(new Equipment(['ownership' => EquipmentOwnership::Owned, 'is_active' => true]));
    }

    public function store(SaveEquipmentRequest $request): RedirectResponse
    {
        $equipment = new Equipment;
        $equipment->fill($this->values($request));
        $equipment->created_by = $request->user()->id;
        $equipment->save();

        return redirect()->route('equipment.show', $equipment)->with('success', __('app.saved'));
    }

    public function show(Request $request, Equipment $equipment): View
    {
        Gate::authorize('equipment.view');

        return view('equipment.show', [
            'equipment' => $equipment->load('type'),
            'projects' => Project::query()->visibleTo($request->user())->whereNotIn('status', Project::closedStatuses())->latest('id')->get(['id', 'number', 'title_ar', 'title_en']),
        ]);
    }

    public function edit(Equipment $equipment): View
    {
        Gate::authorize('equipment.update');

        return $this->form($equipment);
    }

    public function update(SaveEquipmentRequest $request, Equipment $equipment): RedirectResponse
    {
        $equipment->fill($this->values($request))->save();

        return redirect()->route('equipment.show', $equipment)->with('success', __('app.saved'));
    }

    /**
     * @return array<string, mixed>
     */
    private function values(SaveEquipmentRequest $request): array
    {
        return [...$request->safe()->except('is_active'), 'is_active' => $request->boolean('is_active', true)];
    }

    private function form(Equipment $equipment): View
    {
        return view('equipment.form', ['equipment' => $equipment, 'types' => Lookup::options(LookupType::EquipmentType)]);
    }
}
