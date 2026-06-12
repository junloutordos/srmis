<?php

namespace App\Http\Middleware;

use App\Services\Central\InstanceSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the first-run setup wizard: once the instance is marked installed,
 * every /setup route disappears (404) so the wizard cannot be re-run to
 * hijack the deployment.
 */
class EnsureNotInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(InstanceSettings::class)->isInstalled()) {
            abort(404);
        }

        return $next($request);
    }
}
