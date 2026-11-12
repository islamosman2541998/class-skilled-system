<?php

namespace App\Http\Middleware;

use App\Settings\SystemSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class IdleTimeout
{
    public const SESSION_KEY = 'last_activity_at';

    public function __construct(private readonly SystemSettings $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()) {
            $last = $request->session()->get(self::SESSION_KEY);
            $limit = $this->settings->session_idle_minutes * 60;

            if ($last !== null && now()->timestamp - (int) $last > $limit) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors(['email' => __('app.session_expired')]);
            }

            $request->session()->put(self::SESSION_KEY, now()->timestamp);
        }

        return $next($request);
    }
}
