<?php

namespace App\Http\Controllers;

use App\Enums\LookupType;
use App\Http\Requests\SaveLookupRequest;
use App\Models\Lookup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LookupController extends Controller
{
    /** Lists longer than this are not offered for drag ordering. */
    private const REORDER_LIMIT = 300;

    public function index(LookupType $type): View
    {
        Gate::authorize('lookups.view');

        $count = Lookup::query()->ofType($type)->count();

        return view('lookups.index', [
            'type' => $type,
            'types' => LookupType::cases(),
            'parents' => $type->parentType() ? Lookup::options($type->parentType()) : collect(),
            'orderable' => $count > 1 && $count <= self::REORDER_LIMIT
                ? Lookup::query()->ofType($type)->orderBy('sort_order')->orderBy('id')->get(['id', 'name_ar', 'name_en', 'is_active'])
                : collect(),
        ]);
    }

    public function store(SaveLookupRequest $request, LookupType $type): JsonResponse
    {
        Lookup::create([
            ...$request->safe()->only(['name_ar', 'name_en', 'parent_id', 'color']),
            'type' => $type,
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) Lookup::query()->ofType($type)->max('sort_order') + 1,
        ]);

        return response()->json(['message' => __('app.saved')]);
    }

    public function update(SaveLookupRequest $request, LookupType $type, Lookup $lookup): JsonResponse
    {
        abort_unless($lookup->type === $type, 404);

        $lookup->update([
            ...$request->safe()->only(['name_ar', 'name_en', 'parent_id', 'color']),
            'is_active' => $request->boolean('is_active', $lookup->is_active),
        ]);

        return response()->json(['message' => __('app.saved')]);
    }

    public function reorder(Request $request, LookupType $type): JsonResponse
    {
        Gate::authorize('lookups.update');

        $ids = $request->validate(['ids' => ['required', 'array'], 'ids.*' => ['integer']])['ids'];
        $own = Lookup::query()->ofType($type)->whereKey($ids)->get()->keyBy('id');

        DB::transaction(function () use ($ids, $own) {
            $position = 0;

            foreach ($ids as $id) {
                // Ids of other lists are ignored and do not consume a position.
                $own->get($id)?->update(['sort_order' => ++$position]);
            }
        });

        return response()->json(['message' => __('app.saved')]);
    }
}
