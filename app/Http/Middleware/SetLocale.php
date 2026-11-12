<?php

namespace App\Http\Middleware;

use App\Settings\AppearanceSettings;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public const SUPPORTED = ['ar', 'en'];

    public function __construct(private readonly AppearanceSettings $appearance) {}

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->user()?->locale ?? $this->appearance->default_locale;

        if (! in_array($locale, self::SUPPORTED, true)) {
            $locale = 'ar';
        }

        app()->setLocale($locale);
        View::share('dir', $locale === 'ar' ? 'rtl' : 'ltr');

        return $next($request);
    }
}
