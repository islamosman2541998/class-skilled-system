<?php

namespace App\Http\Controllers;

use App\Actions\Resources\SaveEquipmentAssignment;
use App\Http\Requests\SaveEquipmentAssignmentRequest;
use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Assignments of equipment to projects (spec §8.17). */
class EquipmentAssignmentController extends Controller
{
    public function store(SaveEquipmentAssignmentRequest $request, Equipment $equipment, SaveEquipmentAssignment $save): RedirectResponse
    {
        $save->handle([...$request->validated(), 'equipment_id' => $equipment->id], $request->user());

        return back()->with('success', __('resources.equipment.assigned'));
    }

    public function update(SaveEquipmentAssignmentRequest $request, EquipmentAssignment $equipmentAssignment, SaveEquipmentAssignment $save): RedirectResponse
    {
        abort_unless($equipmentAssignment->isVisibleTo($request->user()), 404);

        $save->handle($request->validated(), $request->user(), $equipmentAssignment);

        return back()->with('success', __('app.saved'));
    }

    public function destroy(Request $request, EquipmentAssignment $equipmentAssignment): RedirectResponse
    {
        Gate::authorize('equipment.delete');
        abort_unless($equipmentAssignment->isVisibleTo($request->user()), 404);

        $equipmentAssignment->delete();

        return back()->with('success', __('resources.equipment.unassigned'));
    }
}
