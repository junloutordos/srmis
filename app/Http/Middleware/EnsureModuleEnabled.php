<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-tenant module activation gate.
 *
 * Used two ways:
 *   - on the tenant route group without a parameter: the module is inferred
 *     from the request path (map below), giving blanket coverage;
 *   - per-route with an explicit module: ->middleware('module:chat').
 *
 * Module flags live on the tenant record (data->modules). A module missing
 * from the map defaults to ENABLED so newly added modules do not vanish for
 * existing tenants until an admin explicitly toggles them off.
 */
class EnsureModuleEnabled
{
    /** First path segment (or segment pair) → module key. */
    protected const PATH_MODULE_MAP = [
        // MIS
        'job-requests'       => 'mis',
        'it-job-requests'    => 'mis',
        'itjr'               => 'mis',
        'ict-equipments'     => 'mis',
        'ict-pms'            => 'mis',
        'ict-pms-history'    => 'mis',
        'equipment'          => 'mis',
        'mis'                => 'mis',
        'csm'                => 'mis',

        // General Services
        'vehicle-requests'   => 'general-services',
        'vehicle-bookings'   => 'general-services',
        'vehicles'           => 'general-services',
        'facility-requests'  => 'general-services',
        'facility-bookings'  => 'general-services',
        'facilities'         => 'general-services',
        'work-requests'      => 'general-services',
        'service-requests'   => 'general-services',

        // Data Management
        'data-management'    => 'data-management',
        'users'              => 'data-management',
        'users-roles'        => 'data-management',
        'users-division'     => 'data-management',
        'users-divisions'    => 'data-management',
        'admin'              => 'data-management',

        // Approval Inbox
        'inbox'              => 'approval-inbox',

        // Reports
        'reports'            => 'reports',

        // Chat
        'chat'               => 'chat',
    ];

    public function handle(Request $request, Closure $next, ?string $module = null): Response
    {
        $tenant = function_exists('tenant') ? tenant() : null;

        if ($tenant === null) {
            return $next($request);
        }

        $module ??= $this->inferModule($request);

        if ($module !== null) {
            $modules = (array) ($tenant->modules ?? []);

            if (array_key_exists($module, $modules) && ! $modules[$module]) {
                abort(403, 'This module is not enabled for your campus.');
            }
        }

        return $next($request);
    }

    protected function inferModule(Request $request): ?string
    {
        $first = $request->segment(1);

        // Two-segment prefixes that don't fit the first-segment map.
        if ($first === 'hr' && $request->segment(2) === 'org') {
            return 'data-management';
        }
        if ($first === 'api' && $request->segment(2) === 'chat') {
            return 'chat';
        }
        if ($first === 'api' && $request->segment(2) === 'drivers') {
            return 'general-services';
        }
        if ($first === 'profile' && $request->segment(2) === 'signature') {
            return 'digital-signature';
        }
        if ($first === 'verify') {
            return 'digital-signature';
        }

        return self::PATH_MODULE_MAP[$first] ?? null;
    }
}
