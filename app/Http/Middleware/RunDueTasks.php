<?php

namespace App\Http\Middleware;

use App\Services\DueTasks;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs periodic tasks after the response has been sent, for signed-in users only (spec §3.2).
 */
class RunDueTasks
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->user() !== null) {
            app(DueTasks::class)->runDue();
        }
    }
}
