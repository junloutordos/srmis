<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use Illuminate\Http\Request;

/**
 * Per-tenant module activation toggles (system superadmin only).
 */
class TenantModuleController extends Controller
{
    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'modules'   => ['required', 'array'],
            'modules.*' => ['boolean'],
        ]);

        // Only known modules may be toggled.
        $modules = array_intersect_key($data['modules'], Tenant::MODULES);

        $tenant->modules = array_map(fn ($v) => (bool) $v, $modules)
            + (array) ($tenant->modules ?? []);
        $tenant->save();

        return back()->with('success', "Module settings saved for {$tenant->name}.");
    }
}
