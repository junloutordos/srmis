<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SystemOverviewController extends Controller
{
    private string $dbPrefix;

    public function __construct()
    {
        $this->dbPrefix = config('tenancy.database.prefix', 'srmis_');
    }

    public function index()
    {
        $tenants = Tenant::orderBy('name')->get();

        $stats = $tenants->map(fn (Tenant $t) =>
            Cache::remember('srmis.overview.' . $t->id, 300, fn () => $this->gatherStats($t))
        )->values();

        return Inertia::render('Central/Overview', [
            'stats'        => $stats,
            'moduleLabels' => Tenant::MODULES,
        ]);
    }

    public function refresh()
    {
        Tenant::all()->each(fn (Tenant $t) => Cache::forget('srmis.overview.' . $t->id));
        return back()->with('success', 'Overview refreshed.');
    }

    private function gatherStats(Tenant $tenant): array
    {
        $s = '`' . $this->dbPrefix . $tenant->id . '`';

        $count = function (string $table, string $where = '1') use ($s): ?int {
            try {
                $row = DB::selectOne("SELECT COUNT(*) AS c FROM {$s}.`{$table}` WHERE {$where}");
                return (int) ($row->c ?? 0);
            } catch (\Throwable) {
                return null;
            }
        };

        $itOpen = $count('it_job_requests',
            "status NOT IN ('Request Completed','Rejected by Division Chief','Rejected by OCD')"
        );

        $vehiclePending  = $count('vehicle_requests',  "status = 'Pending'");
        $servicePending  = $count('service_requests',  "status = 'Pending'");
        $workPending     = $count('work_requests',     "status = 'Pending'");
        $facilityPending = $count('facility_requests', "status = 'Pending'");

        $activeUsers = $count('users', "status != 'inactive'");

        $provisioned = $itOpen !== null || $activeUsers !== null;

        return [
            'id'              => $tenant->id,
            'name'            => $tenant->name,
            'campus_code'     => $tenant->campus_code,
            'modules'         => $tenant->modules ?? [],
            'provisioned'     => $provisioned,
            'active_users'    => $activeUsers,
            'it_open'         => $itOpen,
            'vehicle_pending' => $vehiclePending,
            'gs_pending'      => ($servicePending ?? 0) + ($workPending ?? 0) + ($facilityPending ?? 0),
        ];
    }
}
