<?php

namespace App\Http\Middleware;

use App\Settings\IntegrationSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The website authenticates with a shared secret in the X-Api-Key header (spec §10, §12).
 * Nothing passes while no key has been generated.
 */
class VerifyWebsiteKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = app(IntegrationSettings::class)->api_key_hash;
        $given = (string) $request->header('X-Api-Key', '');

        if ($expected === null || $expected === '' || $given === '' || ! hash_equals($expected, hash('sha256', $given))) {
            return response()->json(['message' => __('leads.website.unauthorized')], 401);
        }

        return $next($request);
    }
}
