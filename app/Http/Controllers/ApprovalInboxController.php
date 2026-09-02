<?php

namespace App\Http\Controllers;

use App\Services\ApprovalInboxService;
use App\Services\DigitalSignatureService;
use App\Models\ITJobRequest;
use App\Models\VehicleRequest;
use App\Models\FacilityRequest;
use App\Models\WorkRequest;
use App\Models\ServiceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ApprovalInboxController extends Controller
{
    private const VALID_TYPES = [
        'it_job_requests',
        'vehicle_requests',
        'facility_requests',
        'work_requests',
        'service_requests',
    ];

    /**
     * GET /approvals
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $this->isApprover($user)) {
            abort(403, 'You do not have an approver role.');
        }

        $service = new ApprovalInboxService($user);
        $tabs    = $service->getPendingItems();

        $totalCount = array_sum(array_column($tabs, 'count'));

        $filters = [
            'search' => $request->query('search', ''),
        ];

        $sigService   = app(DigitalSignatureService::class);
        $hasPin       = ! empty($user->signature_pin);
        $signatureUri = $sigService->getSignatureDataUri($user);

        return Inertia::render('Approvals/Inbox', compact('tabs', 'totalCount', 'filters', 'hasPin', 'signatureUri'));
    }

    /**
     * POST /approvals/{type}/{id}/approve
     */
    public function approve(Request $request, string $type, int $id)
    {
        if (! in_array($type, self::VALID_TYPES, true)) {
            abort(404, 'Invalid request type.');
        }

        $user = $request->user();

        try {
            [$model, $record] = $this->resolveRecord($type, $id);

            $this->authoriseApprove($user, $type, $record);
            $this->checkPending($type, $record);

            return $this->delegateApprove($request, $type, $record);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // A raw abort() lacks the X-Inertia header Inertia needs to route
            // the error to the page's onError callback — without this, the
            // frontend's "processing…" UI never resolves. Redirect back with
            // a flashed error instead, the same way validation errors work.
            return back()->withErrors(['message' => $e->getMessage() ?: 'This action could not be completed.']);
        } catch (\Throwable $e) {
            logger()->error('ApprovalInboxController::approve error', [
                'type'  => $type,
                'id'    => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['message' => 'An unexpected error occurred.']);
        }
    }

    /**
     * POST /approvals/{type}/{id}/decline
     */
    public function decline(Request $request, string $type, int $id)
    {
        if (! in_array($type, self::VALID_TYPES, true)) {
            abort(404, 'Invalid request type.');
        }

        $request->validate(['reason' => 'required|string|min:1|max:1000']);

        $user = $request->user();

        try {
            [$model, $record] = $this->resolveRecord($type, $id);

            $this->authoriseApprove($user, $type, $record);
            $this->checkPending($type, $record);

            return $this->delegateDecline($request, $type, $record);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return back()->withErrors(['message' => $e->getMessage() ?: 'This action could not be completed.']);
        } catch (\Throwable $e) {
            logger()->error('ApprovalInboxController::decline error', [
                'type'  => $type,
                'id'    => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->withErrors(['message' => 'An unexpected error occurred.']);
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function isApprover($user): bool
    {
        return $user->hasAnyRole(['Administrator', 'DivisionChief', 'GSU Head', 'GSU Dispatcher', 'OCD', 'FAD Chief'])
            || str_contains($user->position ?? '', 'FAD');
    }

    /**
     * Resolve the record for the given type and id.
     * Returns [modelClass, record].
     * Aborts 404 if not found.
     */
    private function resolveRecord(string $type, int $id): array
    {
        switch ($type) {
            case 'it_job_requests':
                $record = ITJobRequest::find($id);
                if (! $record) abort(404);
                return [ITJobRequest::class, $record];

            case 'vehicle_requests':
                $record = VehicleRequest::find($id);
                if (! $record) abort(404);
                return [VehicleRequest::class, $record];

            case 'facility_requests':
                $record = FacilityRequest::find($id);
                if (! $record) abort(404);
                return [FacilityRequest::class, $record];

            case 'work_requests':
                $record = WorkRequest::find($id);
                if (! $record) abort(404);
                return [WorkRequest::class, $record];

            case 'service_requests':
                $record = ServiceRequest::find($id);
                if (! $record) abort(404);
                return [ServiceRequest::class, $record];


        }

        abort(404);
    }

    /**
     * Verify the authenticated user is authorised to act on this record.
     */
    private function authoriseApprove($user, string $type, $record): void
    {
        $isDC    = $user->hasRole('DivisionChief') || $user->hasRole('Administrator');
        $isFAD   = str_contains($user->position ?? '', 'FAD') || $user->hasRole('FAD Chief') || $user->hasRole('Administrator');
        $isGSU   = $user->hasRole('GSU Head') || $user->hasRole('GSU Dispatcher') || $user->hasRole('Administrator');
        $isOCD   = $user->hasRole('OCD') || $user->hasRole('Administrator');

        switch ($type) {
            case 'it_job_requests':
                if ($isDC && (int) $record->divisionchief_id === (int) $user->id) break;
                if ($isOCD) break;
                abort(403);

            case 'vehicle_requests':
                if ($isDC) {
                    $canAct = (int) ($record->division_chief_id ?? 0) === (int) $user->id;
                    if (! $canAct) {
                        $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                        $canAct = $divisionIds->isNotEmpty()
                            && \App\Models\User::where('id', $record->requestor_id)
                                ->whereIn('division_id', $divisionIds)->exists();
                    }
                    if ($canAct) break;
                }
                if ($isGSU || $isOCD) break;
                abort(403);

            case 'facility_requests':
                if ($isDC) {
                    $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                    $canAct = $divisionIds->isNotEmpty()
                        && \App\Models\User::where('id', $record->requestor_id)
                            ->whereIn('division_id', $divisionIds)->exists();
                    if ($canAct) break;
                }
                if ($isFAD || $isGSU || $isOCD) break;
                abort(403);

            case 'work_requests':
                if ($isDC) {
                    $canAct = (int) ($record->division_chief_id ?? 0) === (int) $user->id;
                    if (! $canAct) {
                        $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                        $canAct = $divisionIds->isNotEmpty()
                            && \App\Models\User::where('id', $record->requester_id)
                                ->whereIn('division_id', $divisionIds)->exists();
                    }
                    if ($canAct) break;
                }
                if ($isFAD) break;
                abort(403);

            case 'service_requests':
                if ($isDC) {
                    $divisionIds = \App\Models\Division::where('division_chief_id', $user->id)->pluck('id');
                    $canAct = $divisionIds->isNotEmpty()
                        && \App\Models\User::where('id', $record->requestor_id)
                            ->whereIn('division_id', $divisionIds)->exists();
                    if ($canAct) break;
                }
                if ($isFAD) break;
                abort(403);


        }
    }

    /**
     * Check the record is still in a pending state. Return 409 if already acted upon.
     */
    private function checkPending(string $type, $record): void
    {
        $pendingStatuses = [
            'it_job_requests'      => ['Pending Division Chief Approval', 'Pending OCD Approval'],
            'vehicle_requests'     => ['Pending Division Chief Approval', 'Approved'],
            'facility_requests'    => ['Pending', 'Pending FAD Approval', 'Pending OCD Approval'],
            'work_requests'        => ['Pending', 'GSU Approved', 'Pending FAD Approval'],
            'service_requests'     => ['Pending', 'Approved'],
        ];

        $allowed = $pendingStatuses[$type] ?? [];
        $status  = $record->status ?? '';

        if (! in_array($status, $allowed, true)) {
            abort(409, 'This request has already been acted upon.');
        }
    }

    /**
     * Delegate the approve action to the existing per-module controller method.
     * Uses the record's current status to determine which approval stage is being acted on.
     */
    private function delegateApprove(Request $request, string $type, $record)
    {
        $user   = $request->user();
        $status = $record->status ?? '';
        $isFAD  = str_contains($user->position ?? '', 'FAD');

        switch ($type) {
            case 'it_job_requests':
                if ($status === 'Pending Division Chief Approval') {
                    $request->merge(['action' => 'approve']);
                    return app(\App\Http\Controllers\ITJobRequestController::class)
                        ->approveByDivisionChief($request, $record);
                }
                $request->merge(['action' => 'approve']);
                return app(\App\Http\Controllers\ITJobRequestController::class)
                    ->approveByOCD($request, $record);

            case 'vehicle_requests':
                if ($status === 'Pending Division Chief Approval') {
                    return app(\App\Http\Controllers\VehicleRequestController::class)
                        ->approveInApp($request, $record);
                }
                $request->merge(['action' => 'approve']);
                return app(\App\Http\Controllers\VehicleRequestController::class)
                    ->approveByOCDInApp($request, $record);

            case 'facility_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\FacilityRequestController::class)
                        ->approveInApp($request, $record);
                }
                $request->merge(['action' => 'approve']);
                return app(\App\Http\Controllers\FacilityRequestController::class)
                    ->ocdAction($request, $record);

            case 'work_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\WorkRequestController::class)
                        ->approveInApp($request, $record);
                }
                // GSU Approved → FAD action
                $request->merge(['action' => 'approve']);
                return app(\App\Http\Controllers\WorkRequestController::class)
                    ->fadAction($request, $record);

            case 'service_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\ServiceRequestController::class)
                        ->approveInApp($request, $record);
                }
                // Approved (DC done) → FAD action
                $request->merge(['action' => 'approve']);
                return app(\App\Http\Controllers\ServiceRequestController::class)
                    ->fadAction($request, $record);


        }

        abort(404);
    }

    /**
     * Delegate the decline action to the existing per-module controller method.
     * Uses the record's current status to determine which approval stage is being acted on.
     */
    private function delegateDecline(Request $request, string $type, $record)
    {
        $user   = $request->user();
        $status = $record->status ?? '';

        switch ($type) {
            case 'it_job_requests':
                if ($status === 'Pending Division Chief Approval') {
                    $request->merge(['action' => 'reject']);
                    return app(\App\Http\Controllers\ITJobRequestController::class)
                        ->approveByDivisionChief($request, $record);
                }
                $request->merge(['action' => 'reject']);
                return app(\App\Http\Controllers\ITJobRequestController::class)
                    ->approveByOCD($request, $record);

            case 'vehicle_requests':
                if ($status === 'Pending Division Chief Approval') {
                    return app(\App\Http\Controllers\VehicleRequestController::class)
                        ->declineInApp($request, $record);
                }
                $request->merge(['action' => 'reject']);
                return app(\App\Http\Controllers\VehicleRequestController::class)
                    ->approveByOCDInApp($request, $record);

            case 'facility_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\FacilityRequestController::class)
                        ->declineInApp($request, $record);
                }
                $request->merge(['action' => 'reject']);
                return app(\App\Http\Controllers\FacilityRequestController::class)
                    ->ocdAction($request, $record);

            case 'work_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\WorkRequestController::class)
                        ->declineInApp($request, $record);
                }
                $request->merge(['action' => 'reject']);
                return app(\App\Http\Controllers\WorkRequestController::class)
                    ->fadAction($request, $record);

            case 'service_requests':
                if ($status === 'Pending') {
                    return app(\App\Http\Controllers\ServiceRequestController::class)
                        ->declineInApp($request, $record);
                }
                $request->merge(['action' => 'reject']);
                return app(\App\Http\Controllers\ServiceRequestController::class)
                    ->fadAction($request, $record);


        }

        abort(404);
    }

}
