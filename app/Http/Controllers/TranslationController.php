<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveTranslationRequest;
use App\Services\ActivityLogger;
use App\Support\TranslationCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Spatie\TranslationLoader\LanguageLine;

/**
 * Interface text editor (spec §5.8, §7). Overrides live in language_lines; the files stay untouched.
 */
class TranslationController extends Controller
{
    public function index(TranslationCatalog $catalog): View
    {
        Gate::authorize('translations.manage');

        return view('settings.translations', [
            'rows' => $catalog->all(),
            'groups' => TranslationCatalog::GROUPS,
        ]);
    }

    public function update(SaveTranslationRequest $request): JsonResponse
    {
        $data = $request->validated();

        $line = LanguageLine::query()->firstOrNew(['group' => $data['group'], 'key' => $data['key']]);
        $before = $line->exists ? $line->text : null;
        $line->text = ['ar' => $data['ar'], 'en' => $data['en']];
        $line->save();

        ActivityLogger::record('translation_updated', null, [
            'key' => $data['group'].'.'.$data['key'],
            'old' => $before,
            'attributes' => $line->text,
        ], 'translations');

        return response()->json(['message' => __('app.saved'), 'ar' => $data['ar'], 'en' => $data['en']]);
    }

    public function reset(SaveTranslationRequest $request, TranslationCatalog $catalog): JsonResponse
    {
        $data = $request->validated();

        LanguageLine::query()->where('group', $data['group'])->where('key', $data['key'])->get()->each->delete();

        ActivityLogger::record('translation_reset', null, ['key' => $data['group'].'.'.$data['key']], 'translations');

        $row = $catalog->all()->first(fn (array $row) => $row['group'] === $data['group'] && $row['key'] === $data['key']);

        return response()->json(['message' => __('settings.translation_reset_done'), 'ar' => $row['ar'] ?? '', 'en' => $row['en'] ?? '']);
    }
}
