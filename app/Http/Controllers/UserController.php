<?php

namespace App\Http\Controllers;

use App\Actions\AttachUploads;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreAttachmentsRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        Gate::authorize('users.view');

        return view('users.index');
    }

    public function create(): View
    {
        Gate::authorize('users.create');

        return $this->form(new User(['locale' => 'ar', 'is_active' => true]));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->safe()->only(['name', 'email', 'phone', 'locale', 'password']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->syncRoles($request->validated('roles', []));

        return redirect()->route('users.index')->with('success', __('app.saved'));
    }

    public function edit(User $user): View
    {
        Gate::authorize('users.update');

        return $this->form($user);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update([
            ...$request->safe()->only(['name', 'email', 'phone', 'locale']),
            'is_active' => $request->boolean('is_active', $user->is_active),
        ]);

        $before = $user->getRoleNames()->sort()->values()->all();
        $after = collect($request->validated('roles', []))->sort()->values()->all();

        $user->syncRoles($after);

        if ($before !== $after) {
            ActivityLogger::record('roles_changed', $user, ['old' => $before, 'attributes' => $after], 'users');
        }

        return redirect()->route('users.index')->with('success', __('app.saved'));
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        Gate::authorize('users.update');

        if ($user->is($request->user())) {
            return back()->with('error', __('users.cannot_deactivate_self'));
        }

        if ($user->isLastActiveSuperAdmin()) {
            return back()->with('error', __('users.last_admin'));
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', __('app.saved'));
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->update(['password' => $request->validated('password')]);

        return back()->with('success', __('users.password_reset_done'));
    }

    public function attach(StoreAttachmentsRequest $request, User $user, AttachUploads $attach): RedirectResponse
    {
        Gate::authorize('users.update');

        $attach->handle($user, $request->validated('uploads'), $request->user(), $request->meta());

        return back()->with('success', __('uploads.attached'));
    }

    private function form(User $user): View
    {
        return view('users.form', [
            'user' => $user,
            'roles' => Role::query()->orderBy('id')->get(),
            'assigned' => $user->exists ? $user->getRoleNames()->all() : [],
        ]);
    }
}
