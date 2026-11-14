<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The signed-in user's own notifications (spec §5.5). Nobody can open another user's notification.
 */
class NotificationController extends Controller
{
    public function index(): View
    {
        return view('notifications.index');
    }

    /** The ten newest, as an HTML fragment for the topbar dropdown. */
    public function latest(Request $request): View
    {
        return view('notifications.latest', [
            'notifications' => $request->user()->notifications()->latest()->limit(10)->get(),
        ]);
    }

    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return redirect($this->safeUrl((string) ($notification->data['url'] ?? '')));
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', __('notifications.all_read'));
    }

    /** Only paths inside this application are followed. */
    private function safeUrl(string $url): string
    {
        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        if (parse_url($url, PHP_URL_HOST) === parse_url((string) config('app.url'), PHP_URL_HOST) && parse_url($url, PHP_URL_HOST) !== null) {
            return $url;
        }

        return route('notifications.index');
    }
}
