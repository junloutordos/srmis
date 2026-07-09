<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;
use App\Models\ITJobRequest;
use App\Models\VehicleRequest;
use App\Models\FacilityRequest;
use App\Models\ServiceRequest;
use App\Models\WorkRequest;
use App\Services\ApprovalInboxService;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    // app/Http/Middleware/HandleInertiaRequests.php
    public function share(Request $request): array
    {
        $authUser = $request->user();

        // Central (system superadmin) sessions resolve a CentralUser — it has
        // no roles/permissions relations. The central pages don't consume the
        // tenant-shaped auth payload, so share it as a guest.
        if ($authUser && ! $authUser instanceof \App\Models\User) {
            $authUser = null;
        }

        if ($authUser) {
            $authUser->loadMissing(['roles', 'primaryUnitAssignment.unit:id,name,code,type']);
        }
        $userRoles = $authUser ? $authUser->roles : collect();

        return array_merge(parent::share($request), [
            // Current campus (single-domain tenancy) — null on guest pages
            'campus' => fn () => tenant()
                ? ['id' => tenant('id'), 'name' => tenant('name'), 'code' => tenant('campus_code')]
                : null,
            'auth' => [
                'user' => $authUser
                    ? [
                        'id' => $authUser->id,
                        'name' => $authUser->name,
                        'role' => $userRoles->first(),                         // backward compat: primary role object
                        'roles' => $userRoles->values(),                       // all role objects [{id, name}]
                        'roleNames' => $userRoles->pluck('name')->toArray(),   // ['Staff', 'DivisionChief']
                        'position' => $authUser->position,
                        'email' => $authUser->email,
                        // expose numeric/string role ids for client-side checks (may be CSV)
                        'role_id' => $userRoles->pluck('id')->implode(','),
                        'sex' => $authUser->sex,
                        'profile_picture' => $authUser->profile_picture
                            ? $this->s3Url($authUser->profile_picture)
                            : null,
                        'electronic_signature' => $authUser->electronic_signature
                            ? $this->s3Url($authUser->electronic_signature)
                            : null,
                        'needsSignatureSetup' => empty($authUser->electronic_signature) || empty($authUser->signature_pin),
                        'permissions' => $authUser->getPermissions(),
                        'primary_unit' => fn () => $authUser->primaryUnitAssignment?->unit
                            ? [
                                'id'         => $authUser->primaryUnitAssignment->unit->id,
                                'name'       => $authUser->primaryUnitAssignment->unit->name,
                                'code'       => $authUser->primaryUnitAssignment->unit->code,
                                'type'       => $authUser->primaryUnitAssignment->unit->type,
                              ]
                            : null,
                    ]
                    : null,
            ],
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
            ],
            // ── Sidebar badge counts — cached 60s to reduce DB queries ────────
            'itJobRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    $cacheKey = 'badge.it_requests.u' . $user->id;
                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        if ($user->hasPermission('it.requests.manage')) {
                            return ITJobRequest::whereIn('status', ['In Progress'])->count();
                        }
                        return ITJobRequest::whereIn('status', ['In Progress'])->where('user_id', $user->id)->count();
                    });
                } catch (\Throwable $e) { return 0; }
            },
            'vehicleRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    $cacheKey = 'badge.vehicles.u' . $user->id;
                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        return VehicleRequest::where('status', 'Pending')->where('requestor_id', $user->id)->count();
                    });
                } catch (\Throwable $e) { return 0; }
            },
            'facilityRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    $cacheKey = 'badge.facility.u' . $user->id;
                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        return FacilityRequest::where('status', 'Pending')->where('requestor_id', $user->id)->count();
                    });
                } catch (\Throwable $e) { return 0; }
            },
            'serviceRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    $cacheKey = 'badge.service.u' . $user->id;
                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        return ServiceRequest::where('status', 'Pending')->where('requestor_id', $user->id)->count();
                    });
                } catch (\Throwable $e) { return 0; }
            },
            'workRequestsNotificationCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    $cacheKey = 'badge.work.u' . $user->id;
                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        return WorkRequest::where('status', 'Pending')->where('requester_id', $user->id)->count();
                    });
                } catch (\Throwable $e) { return 0; }
            },
            'approvalInboxCount' => function () use ($request) {
                try {
                    $user = $request->user();
                    if (!$user) return 0;
                    $cacheKey = 'badge.approvals_inbox.u' . $user->id;
                    return Cache::remember($cacheKey, 60, function () use ($user) {
                        return (new ApprovalInboxService($user))->totalPendingCount();
                    });
                } catch (\Throwable $e) { return 0; }
            },
            'appVersion' => function () {
                try {
                    return Cache::remember('app.version', 3600, function () {
                        $versions = \App\Models\AppVersion::orderBy('date', 'desc')->get();
                        $current  = $versions->firstWhere('is_current', true) ?? $versions->first();
                        return [
                            'current' => $current?->version ?? '1.0.0',
                            'history' => $versions->map(fn ($v) => [
                                'version' => $v->version,
                                'date'    => $v->date->format('Y-m-d'),
                                'remarks' => $v->remarks,
                            ])->values()->toArray(),
                        ];
                    });
                } catch (\Throwable $e) {
                    return ['current' => '1.0.0', 'history' => []];
                }
            },
        ]);
    }

    private function s3Url(?string $path): ?string
    {
        if (! $path) return null;
        if (str_starts_with($path, 'http')) return $path;
        try {
            return Storage::disk('s3')->temporaryUrl($path, now()->addHour());
        } catch (\Throwable) {
            return route('storage.proxy', ['path' => $path]);
        }
    }

}
