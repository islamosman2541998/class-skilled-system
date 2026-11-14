<?php

namespace App\Http\Controllers;

use App\Enums\LookupType;
use App\Models\Lookup;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Reference screens approved before the business modules are built (spec §4.6).
 */
class DesignController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('settings.manage');

        return view('design.index', [
            'services' => Lookup::options(LookupType::Service),
            'user' => $request->user(),
        ]);
    }

    public function record(Request $request): View
    {
        Gate::authorize('settings.manage');

        $tabs = collect(['overview', 'stages', 'payments', 'attachments', 'activity'])
            ->mapWithKeys(fn (string $tab) => [$tab => __('design.tab_'.$tab)])->all();
        $active = array_key_exists((string) $request->query('tab'), $tabs) ? (string) $request->query('tab') : 'overview';

        return view('design.record', [
            'tabs' => $tabs,
            'active' => $active,
            'emirates' => Lookup::options(LookupType::Emirate),
            'managers' => User::query()->where('is_active', true)->orderBy('name')->limit(20)->get(),
            'user' => $request->user(),
        ]);
    }
}
