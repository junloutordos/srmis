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

    // ── System-wide overview ───────────────────────────────────────────────────

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
        return back()->with('success', 'Overview data refreshed.');
    }

    // ── Per-campus detail ──────────────────────────────────────────────────────

    public function show(string $slug)
    {
        $tenant = Tenant::findOrFail($slug);

        $detail = Cache::remember('srmis.campus_detail.' . $slug, 120,
            fn () => $this->gatherCampusDetail($tenant)
        );

        return Inertia::render('Central/CampusDetail', [
            'campus'       => [
                'id'          => $tenant->id,
                'name'        => $tenant->name,
                'campus_code' => $tenant->campus_code,
                'modules'     => $tenant->modules ?? [],
            ],
            'detail'       => $detail,
            'moduleLabels' => Tenant::MODULES,
        ]);
    }

    public function campusRefresh(string $slug)
    {
        Cache::forget('srmis.campus_detail.' . $slug);
        Cache::forget('srmis.overview.' . $slug);
        return back()->with('success', 'Campus data refreshed.');
    }

    // ── Data gathering ─────────────────────────────────────────────────────────

    private function schema(string $tenantId): string
    {
        return '`' . $this->dbPrefix . $tenantId . '`';
    }

    private function cnt(string $s, string $table, string $where = '1'): ?int
    {
        try {
            $row = DB::selectOne("SELECT COUNT(*) AS c FROM {$s}.`{$table}` WHERE {$where}");
            return (int) ($row->c ?? 0);
        } catch (\Throwable) {
            return null;
        }
    }

    private function qry(string $s, string $sql, array $bindings = []): array
    {
        try {
            return DB::select(str_replace('{S}', $s, $sql), $bindings);
        } catch (\Throwable) {
            return [];
        }
    }

    private function gatherStats(Tenant $tenant): array
    {
        $s          = $this->schema($tenant->id);
        $thisMonth  = now()->startOfMonth()->toDateString();

        $itOpen = $this->cnt($s, 'it_job_requests',
            "status NOT IN ('Request Completed','Rejected by Division Chief','Rejected by OCD')"
        );
        $itCompletedMonth = $this->cnt($s, 'it_job_requests',
            "status = 'Request Completed' AND completed_at >= '{$thisMonth}'"
        );
        $itTotal = $this->cnt($s, 'it_job_requests');

        $vehicleRows    = $this->qry($s, "SELECT status, COUNT(*) AS c FROM {S}.`vehicle_requests` GROUP BY status");
        $vehicleMap     = collect($vehicleRows)->pluck('c', 'status');
        $vehiclePending = (int) ($vehicleMap['Pending'] ?? 0);
        $vehicleTotal   = $vehicleMap->sum();

        $gsPending = 0;
        foreach (['service_requests', 'work_requests', 'facility_requests'] as $tbl) {
            $gsPending += $this->cnt($s, $tbl, "status = 'Pending'") ?? 0;
        }

        $activeUsers = $this->cnt($s, 'users', "status != 'inactive'");

        // all request types combined this month
        $monthRow = null;
        try {
            $monthRow = DB::selectOne(
                "(SELECT COUNT(*) AS c FROM {$s}.`it_job_requests`   WHERE created_at >= ?)
                 UNION ALL
                 (SELECT COUNT(*) FROM {$s}.`vehicle_requests`  WHERE created_at >= ?)
                 UNION ALL
                 (SELECT COUNT(*) FROM {$s}.`service_requests`  WHERE created_at >= ?)
                 UNION ALL
                 (SELECT COUNT(*) FROM {$s}.`work_requests`     WHERE created_at >= ?)
                 UNION ALL
                 (SELECT COUNT(*) FROM {$s}.`facility_requests` WHERE created_at >= ?)",
                array_fill(0, 5, $thisMonth)
            );
        } catch (\Throwable) {}

        // UNION ALL returns multiple rows — sum them
        $requestsThisMonth = 0;
        try {
            $monthRows = DB::select(
                "(SELECT COUNT(*) AS c FROM {$s}.`it_job_requests`   WHERE created_at >= ?)
                 UNION ALL
                 (SELECT COUNT(*) AS c FROM {$s}.`vehicle_requests`  WHERE created_at >= ?)
                 UNION ALL
                 (SELECT COUNT(*) AS c FROM {$s}.`service_requests`  WHERE created_at >= ?)
                 UNION ALL
                 (SELECT COUNT(*) AS c FROM {$s}.`work_requests`     WHERE created_at >= ?)
                 UNION ALL
                 (SELECT COUNT(*) AS c FROM {$s}.`facility_requests` WHERE created_at >= ?)",
                array_fill(0, 5, $thisMonth)
            );
            $requestsThisMonth = collect($monthRows)->sum('c');
        } catch (\Throwable) {}

        $provisioned = $itOpen !== null || $activeUsers !== null;

        return [
            'id'                  => $tenant->id,
            'name'                => $tenant->name,
            'campus_code'         => $tenant->campus_code,
            'modules'             => $tenant->modules ?? [],
            'provisioned'         => $provisioned,
            'active_users'        => $activeUsers,
            'it_open'             => $itOpen,
            'it_completed_month'  => $itCompletedMonth,
            'it_total'            => $itTotal,
            'vehicle_pending'     => $vehiclePending,
            'vehicle_total'       => $vehicleTotal,
            'gs_pending'          => $gsPending,
            'requests_this_month' => $requestsThisMonth,
        ];
    }

    private function gatherCampusDetail(Tenant $tenant): array
    {
        $s         = $this->schema($tenant->id);
        $thisMonth = now()->startOfMonth()->toDateString();
        $thisWeek  = now()->startOfWeek()->toDateString();

        // ── IT Job Requests ───────────────────────────────────────────────────
        $itRows = $this->qry($s, "SELECT status, COUNT(*) AS c FROM {S}.`it_job_requests` GROUP BY status");
        $itRaw  = collect($itRows)->pluck('c', 'status')->map(fn ($c) => (int) $c)->toArray();

        $dcStatuses  = ['For Approval of DC', 'Submitted IT Job Request', 'Pending Division Chief Approval'];
        $inpStatuses = ['In Progress', 'MIS Assessed the Request', 'Division Chief Approved', 'OCD Approved'];
        $rejStatuses = ['Rejected by Division Chief', 'Rejected by OCD'];

        $itStatuses = [
            'For DC Approval'      => (int) array_sum(array_map(fn ($s2) => $itRaw[$s2] ?? 0, $dcStatuses)),
            'Pending OCD Approval' => (int) ($itRaw['Pending OCD Approval'] ?? 0),
            'In Progress'          => (int) array_sum(array_map(fn ($s2) => $itRaw[$s2] ?? 0, $inpStatuses)),
            'Completed'            => (int) ($itRaw['Request Completed'] ?? 0),
            'Rejected'             => (int) array_sum(array_map(fn ($s2) => $itRaw[$s2] ?? 0, $rejStatuses)),
        ];

        $itCategoryRows = $this->qry($s,
            "SELECT category, COUNT(*) AS c FROM {S}.`it_job_requests` GROUP BY category ORDER BY c DESC LIMIT 6"
        );
        $itPriorityRows = $this->qry($s,
            "SELECT priority, COUNT(*) AS c FROM {S}.`it_job_requests` GROUP BY priority ORDER BY c DESC"
        );

        $itRatingRow = null;
        try {
            $itRatingRow = DB::selectOne(
                "SELECT ROUND(AVG(rating),1) AS avg_r, COUNT(rating) AS cnt FROM {$s}.`it_job_requests` WHERE rating IS NOT NULL"
            );
        } catch (\Throwable) {}

        $itTimingRows = [];
        try {
            $itTimingRows = DB::select(
                "SELECT
                    COUNT(*) AS total,
                    SUM(created_at >= ?) AS this_month,
                    SUM(created_at >= ?) AS this_week,
                    SUM(status = 'Request Completed' AND completed_at >= ?) AS completed_month
                FROM {$s}.`it_job_requests`",
                [$thisMonth, $thisWeek, $thisMonth]
            );
        } catch (\Throwable) {}
        $itTiming = $itTimingRows[0] ?? null;

        // ── Vehicle Requests ──────────────────────────────────────────────────
        $vehRows     = $this->qry($s, "SELECT status, COUNT(*) AS c FROM {S}.`vehicle_requests` GROUP BY status");
        $vehMap      = collect($vehRows)->pluck('c', 'status')->map(fn ($c) => (int) $c)->toArray();
        $vehTiming   = $this->timingRow($s, 'vehicle_requests', $thisMonth, $thisWeek);

        $vehicleStatuses = [
            'Pending'      => $vehMap['Pending']      ?? 0,
            'OCD Approved' => $vehMap['OCD Approved'] ?? 0,
            'Approved'     => $vehMap['Approved']     ?? 0,
            'Declined'     => $vehMap['Declined']     ?? 0,
        ];

        // ── Work Requests ─────────────────────────────────────────────────────
        $workRows    = $this->qry($s, "SELECT status, COUNT(*) AS c FROM {S}.`work_requests` GROUP BY status");
        $workMap     = collect($workRows)->pluck('c', 'status')->map(fn ($c) => (int) $c)->toArray();
        $workTiming  = $this->timingRow($s, 'work_requests', $thisMonth, $thisWeek);

        $workStatuses = [
            'Pending'           => $workMap['Pending']           ?? 0,
            'Division Approved' => $workMap['Division Approved'] ?? 0,
            'Approved'          => $workMap['Approved']          ?? 0,
            'Completed'         => $workMap['Completed']         ?? 0,
            'Declined'          => $workMap['Declined']          ?? 0,
        ];

        // ── Service Requests ──────────────────────────────────────────────────
        $svcRows    = $this->qry($s, "SELECT status, COUNT(*) AS c FROM {S}.`service_requests` GROUP BY status");
        $svcMap     = collect($svcRows)->pluck('c', 'status')->map(fn ($c) => (int) $c)->toArray();
        $svcTiming  = $this->timingRow($s, 'service_requests', $thisMonth, $thisWeek);

        $serviceStatuses = [
            'Pending'      => $svcMap['Pending']      ?? 0,
            'FAD Approved' => $svcMap['FAD Approved'] ?? 0,
            'GSU Approved' => $svcMap['GSU Approved'] ?? 0,
            'Approved'     => $svcMap['Approved']     ?? 0,
            'Declined'     => ($svcMap['Declined'] ?? 0) + ($svcMap['FAD Declined'] ?? 0),
        ];

        // ── Facility Requests ─────────────────────────────────────────────────
        $facRows    = $this->qry($s, "SELECT status, COUNT(*) AS c FROM {S}.`facility_requests` GROUP BY status");
        $facMap     = collect($facRows)->pluck('c', 'status')->map(fn ($c) => (int) $c)->toArray();
        $facTiming  = $this->timingRow($s, 'facility_requests', $thisMonth, $thisWeek);

        $facilityStatuses = [
            'Pending'  => $facMap['Pending']  ?? 0,
            'Approved' => $facMap['Approved'] ?? 0,
            'Declined' => $facMap['Declined'] ?? 0,
        ];

        // ── Users ─────────────────────────────────────────────────────────────
        $activeUsers  = $this->cnt($s, 'users', "status != 'inactive'") ?? 0;
        $empCatRows   = $this->qry($s,
            "SELECT emp_category AS label, COUNT(*) AS c FROM {S}.`users`
             WHERE status != 'inactive' AND emp_category IS NOT NULL AND emp_category != ''
             GROUP BY emp_category ORDER BY c DESC"
        );
        $sexRows      = $this->qry($s,
            "SELECT sex AS label, COUNT(*) AS c FROM {S}.`users`
             WHERE status != 'inactive' AND sex IS NOT NULL AND sex != ''
             GROUP BY sex ORDER BY c DESC"
        );

        // ── ICT ───────────────────────────────────────────────────────────────
        $ictRows = $this->qry($s, "SELECT status AS label, COUNT(*) AS c FROM {S}.`ict_equipments` GROUP BY status ORDER BY c DESC");
        $pmsRows = $this->qry($s, "SELECT status AS label, COUNT(*) AS c FROM {S}.`ict_pms`        GROUP BY status ORDER BY c DESC");

        // ── Aggregate totals ──────────────────────────────────────────────────
        $toRows = fn (array $r) => array_map(fn ($row) => ['label' => $row->label, 'count' => (int) $row->c], $r);

        $itMonthTotal = (int) ($itTiming?->this_month ?? 0);
        $itWeekTotal  = (int) ($itTiming?->this_week  ?? 0);

        $totalThisMonth = $itMonthTotal
            + (int) ($vehTiming?->this_month ?? 0)
            + (int) ($workTiming?->this_month ?? 0)
            + (int) ($svcTiming?->this_month  ?? 0)
            + (int) ($facTiming?->this_month  ?? 0);

        $totalThisWeek = $itWeekTotal
            + (int) ($vehTiming?->this_week  ?? 0)
            + (int) ($workTiming?->this_week  ?? 0)
            + (int) ($svcTiming?->this_week   ?? 0)
            + (int) ($facTiming?->this_week   ?? 0);

        $totalAllTime = ((int) ($itTiming?->total ?? 0))
            + collect($vehRows)->sum('c')
            + collect($workRows)->sum('c')
            + collect($svcRows)->sum('c')
            + collect($facRows)->sum('c');

        return [
            'total_all_time'      => $totalAllTime,
            'total_this_month'    => $totalThisMonth,
            'total_this_week'     => $totalThisWeek,
            'it_completed_month'  => (int) ($itTiming?->completed_month ?? 0),
            'it_statuses'         => $itStatuses,
            'it_categories'       => array_map(fn ($r) => ['label' => $r->category, 'count' => (int) $r->c], $itCategoryRows),
            'it_priorities'       => array_map(fn ($r) => ['label' => $r->priority ?? 'normal', 'count' => (int) $r->c], $itPriorityRows),
            'it_avg_rating'       => $itRatingRow ? (float) $itRatingRow->avg_r : null,
            'it_rated_count'      => $itRatingRow ? (int) $itRatingRow->cnt : 0,
            'it_this_month'       => $itMonthTotal,
            'it_this_week'        => $itWeekTotal,
            'vehicle_statuses'    => $vehicleStatuses,
            'vehicle_this_month'  => (int) ($vehTiming?->this_month ?? 0),
            'work_statuses'       => $workStatuses,
            'work_this_month'     => (int) ($workTiming?->this_month ?? 0),
            'service_statuses'    => $serviceStatuses,
            'service_this_month'  => (int) ($svcTiming?->this_month ?? 0),
            'facility_statuses'   => $facilityStatuses,
            'facility_this_month' => (int) ($facTiming?->this_month ?? 0),
            'active_users'        => $activeUsers,
            'emp_categories'      => $toRows($empCatRows),
            'sex_breakdown'       => $toRows($sexRows),
            'ict_equipment'       => $toRows($ictRows),
            'pms_statuses'        => $toRows($pmsRows),
        ];
    }

    private function timingRow(string $s, string $table, string $thisMonth, string $thisWeek): ?object
    {
        try {
            $rows = DB::select(
                "SELECT SUM(created_at >= ?) AS this_month, SUM(created_at >= ?) AS this_week
                 FROM {$s}.`{$table}`",
                [$thisMonth, $thisWeek]
            );
            return $rows[0] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }
}
