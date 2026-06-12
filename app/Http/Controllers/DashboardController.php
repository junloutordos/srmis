<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Division;
use App\Models\ITJobRequest;
use App\Models\VehicleRequest;
use App\Models\FacilityRequest;
use App\Models\ServiceRequest;
use App\Models\WorkRequest;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        /*
        |--------------------------------------------------------------------------
        | Employee Counts (Faculty, Staff, Total)
        |--------------------------------------------------------------------------
        */
        $totalEmployees      = 0;
        $facultyCount        = 0;
        $staffCount          = 0;
        $activeDivisions     = 0;
        $employeeMaleCount   = 0;
        $employeeFemaleCount = 0;
        $employeesByDivision = [];

        try {
            // Base scope: active employees with an assigned employee category
            $activeEmployeeBase = fn () => User::where('status', '!=', 'inactive')
                ->whereNotNull('emp_category')
                ->where('emp_category', '!=', '');

            $totalEmployees = $activeEmployeeBase()->count();

            $facultyCount = $activeEmployeeBase()
                ->whereHas('roles', fn ($q) => $q->where('roles.name', 'Faculty'))
                ->count();

            $staffCount = $activeEmployeeBase()
                ->whereHas('roles', fn ($q) => $q->where('roles.name', 'Staff'))
                ->count();

            $activeDivisions     = Division::where('status', 'active')->count();
            $employeeMaleCount   = $activeEmployeeBase()->whereRaw("LOWER(TRIM(COALESCE(sex,''))) IN ('male','m')")->count();
            $employeeFemaleCount = $activeEmployeeBase()->whereRaw("LOWER(TRIM(COALESCE(sex,''))) IN ('female','f')")->count();

            $divRows = DB::table('users')
                ->join('divisions', 'users.division_id', '=', 'divisions.id')
                ->select(
                    DB::raw("COALESCE(NULLIF(TRIM(divisions.acronym),''), divisions.division_name) as division"),
                    DB::raw('COUNT(*) as cnt')
                )
                ->where('users.status', '!=', 'inactive')
                ->whereNotNull('users.emp_category')
                ->where('users.emp_category', '!=', '')
                ->groupBy('divisions.id', 'division')
                ->orderByDesc('cnt')
                ->take(10)
                ->get();

            $employeesByDivision = $divRows->map(fn ($d) => [
                'division' => $d->division,
                'count'    => (int) $d->cnt,
            ])->values()->toArray();
        } catch (\Throwable $e) {
            logger()->warning('Employee analytics error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | IT Job Requests by Category
        |--------------------------------------------------------------------------
        */
        $itjrByCategory = [];
        try {
            $itjrByCategory = ITJobRequest::select('category', DB::raw('COUNT(*) as total'))
                ->whereNotNull('category')
                ->where('category', '!=', '')
                ->groupBy('category')
                ->get()
                ->map(fn ($r) => ['category' => $r->category, 'total' => (int) $r->total])
                ->values()
                ->toArray();
        } catch (\Throwable $e) {
            logger()->warning('ITJR by category error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | Request Overview (Active / Pending Counts per Module)
        |--------------------------------------------------------------------------
        */
        $requestOverview      = [];
        $totalPendingRequests = 0;

        try {
            $requestOverview = [
                [
                    'label'     => 'IT Job Requests',
                    'pending'   => ITJobRequest::where('status', 'In Progress')->count(),
                    'completed' => ITJobRequest::whereIn('status', ['Acted by MIS', 'Request Completed'])->count(),
                    'total'     => ITJobRequest::count(),
                ],
                [
                    'label'     => 'Vehicle Requests',
                    'pending'   => VehicleRequest::where('status', 'Pending')->count(),
                    'completed' => VehicleRequest::where('status', 'OCD Approved')->count(),
                    'total'     => VehicleRequest::count(),
                ],
                [
                    'label'     => 'Facility Requests',
                    'pending'   => FacilityRequest::where('status', 'Pending')->count(),
                    'completed' => FacilityRequest::where('status', 'FAD Approved')->count(),
                    'total'     => FacilityRequest::count(),
                ],
                [
                    'label'     => 'Service Requests',
                    'pending'   => ServiceRequest::where('status', 'Pending')->count(),
                    'completed' => ServiceRequest::where('status', 'FAD Approved')->count(),
                    'total'     => ServiceRequest::count(),
                ],
                [
                    'label'     => 'Work Requests',
                    'pending'   => WorkRequest::whereIn('status', ['Pending', 'GSU Approved', 'FAD Approved'])->count(),
                    'completed' => WorkRequest::where('status', 'Completed')->count(),
                    'total'     => WorkRequest::count(),
                ],
            ];
            $totalPendingRequests = (int) collect($requestOverview)->sum('pending');
        } catch (\Throwable $e) {
            logger()->warning('Request overview error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | Monthly Request Trends (Last 6 Months)
        |--------------------------------------------------------------------------
        */
        $monthlyTrends = ['labels' => [], 'datasets' => []];
        try {
            $months = [];
            for ($i = 5; $i >= 0; $i--) {
                $months[] = Carbon::now()->startOfMonth()->subMonths($i);
            }

            $monthLabels = array_map(fn ($m) => $m->format('M Y'), $months);
            $colors      = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4'];

            $modules = [
                ['label' => 'IT Job Requests',   'model' => ITJobRequest::class],
                ['label' => 'Vehicle Requests',  'model' => VehicleRequest::class],
                ['label' => 'Facility Requests', 'model' => FacilityRequest::class],
                ['label' => 'Service Requests',  'model' => ServiceRequest::class],
                ['label' => 'Work Requests',     'model' => WorkRequest::class],
            ];

            $datasets = [];
            foreach ($modules as $ci => $module) {
                $data = array_map(
                    fn ($m) => $module['model']::whereMonth('created_at', $m->month)
                        ->whereYear('created_at', $m->year)
                        ->count(),
                    $months
                );
                $datasets[] = [
                    'label' => $module['label'],
                    'data'  => $data,
                    'color' => $colors[$ci],
                ];
            }

            $monthlyTrends = ['labels' => $monthLabels, 'datasets' => $datasets];
        } catch (\Throwable $e) {
            logger()->warning('Monthly trends error: ' . $e->getMessage());
        }

        /*
        |--------------------------------------------------------------------------
        | Render Dashboard
        |--------------------------------------------------------------------------
        */
        return Inertia::render('Dashboard', [
            'campusName' => tenant()?->name,

            // Employee stats
            'totalEmployees'      => $totalEmployees,
            'facultyCount'        => $facultyCount,
            'staffCount'          => $staffCount,
            'employeeMaleCount'   => $employeeMaleCount,
            'employeeFemaleCount' => $employeeFemaleCount,
            'activeDivisions'     => $activeDivisions,
            'employeesByDivision' => $employeesByDivision,

            // IT Job Requests
            'itjrByCategory' => $itjrByCategory,

            // Request overview
            'requestOverview'      => $requestOverview,
            'totalPendingRequests' => $totalPendingRequests,

            // Monthly trends
            'monthlyTrends' => $monthlyTrends,
        ]);
    }
}
