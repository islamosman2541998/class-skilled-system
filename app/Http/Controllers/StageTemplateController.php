<?php

namespace App\Http\Controllers;

use App\Enums\LookupType;
use App\Http\Requests\SaveStageTemplatesRequest;
use App\Models\Lookup;
use App\Models\StageTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Stage templates per service (spec §7): the stages, their order and weights copied into a new project.
 */
class StageTemplateController extends Controller
{
    public function edit(Request $request): View
    {
        Gate::authorize('settings.manage');

        $services = Lookup::query()->ofType(LookupType::Service)->orderBy('sort_order')->get();
        $service = $services->firstWhere('id', $request->integer('service')) ?? $services->first();

        return view('settings.stage-templates', [
            'services' => $services,
            'service' => $service,
            'stages' => old('stages', StageTemplate::forService($service?->id)->map->only(['name_ar', 'name_en', 'weight'])->all()),
        ]);
    }

    public function update(SaveStageTemplatesRequest $request): RedirectResponse
    {
        $service = Lookup::query()->ofType(LookupType::Service)->findOrFail($request->integer('service'));

        DB::transaction(function () use ($request, $service) {
            StageTemplate::query()->where('service_id', $service->id)->delete();

            foreach ($request->validated('stages') as $index => $row) {
                StageTemplate::query()->create([
                    'service_id' => $service->id,
                    'name_ar' => $row['name_ar'],
                    'name_en' => $row['name_en'] ?? null,
                    'weight' => (string) $row['weight'],
                    'sort_order' => $index + 1,
                ]);
            }
        });

        return redirect()->route('settings.stage-templates', ['service' => $service->id])->with('success', __('app.saved'));
    }
}
