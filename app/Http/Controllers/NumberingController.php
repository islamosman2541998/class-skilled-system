<?php

namespace App\Http\Controllers;

use App\Enums\DocumentType;
use App\Http\Requests\UpdateSequenceRequest;
use App\Models\DocumentSequence;
use App\Services\NumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class NumberingController extends Controller
{
    public function edit(NumberGenerator $generator): View
    {
        Gate::authorize('settings.manage');

        DocumentSequence::ensureAll();

        $sequences = DocumentSequence::all()->keyBy(fn (DocumentSequence $sequence) => $sequence->type->value);

        return view('settings.numbering', [
            'types' => DocumentType::cases(),
            'sequences' => $sequences,
            'previews' => collect(DocumentType::cases())->mapWithKeys(fn (DocumentType $type) => [$type->value => $generator->preview($type)]),
        ]);
    }

    public function update(UpdateSequenceRequest $request): RedirectResponse
    {
        DocumentSequence::ensureAll();

        DB::transaction(function () use ($request) {
            foreach (DocumentSequence::query()->lockForUpdate()->get() as $sequence) {
                $row = $request->validated('sequences.'.$sequence->type->value);

                $sequence->update([
                    'prefix' => strtoupper($row['prefix']),
                    'padding' => (int) $row['padding'],
                    'next_number' => (int) $row['next_number'],
                    'include_year' => (bool) ($row['include_year'] ?? false),
                    'reset_yearly' => (bool) ($row['reset_yearly'] ?? false),
                ]);
            }
        });

        return redirect()->route('settings.numbering')->with('success', __('app.saved'));
    }
}
