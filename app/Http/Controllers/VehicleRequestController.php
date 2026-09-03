<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\VehicleRequest;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use App\Mail\VehicleRequestCreatedMail;
use App\Models\User;
use Carbon\Carbon;
use App\Enums\ApprovalStep;
use App\Services\SnapshotService;
use App\Services\DigitalSignatureService;
use App\Services\ApprovalRoutingService;
use App\Http\Traits\SignsDocuments;

class VehicleRequestController extends Controller
{
    use SignsDocuments;

    public function __construct(
        private SnapshotService         $snapshots,
        private DigitalSignatureService $sigService,
    ) {}
    /**
     * Display a listing of vehicle requests.
     */
    public function index(Request $request)
    {

        $user = $request->user();
        $canViewAll = $user->hasAnyRole(['Administrator', 'GSU Head', 'GSU Dispatcher', ApprovalRoutingService::finalApproverRole()]);

        $requests = VehicleRequest::with(['requester:id,name', 'driver:id,name'])->latest();

        if (! $canViewAll) {
            if ($user->hasRole('DivisionChief')) {
                $requests->where(function ($q) use ($user) {
                    $q->where('requestor_id', $user->id)
                      ->orWhere('division_chief_id', $user->id);
                });
            } else {
                $requests->where('requestor_id', $user->id);
            }
        }

        $requests = $requests->get();

        // also fetch vehicles for dropdown (only available vehicles) — shown as an
        // optional "preferred vehicle type" hint on the create form.
        $vehicles = \App\Models\Vehicle::where('status','!=','Under Repair')->orderBy('name')->get();

        // Division Chief is now auto-resolved from the requestor's division —
        // no dropdown is shown on the create form.

        $isDivisionChief = $user->hasRole('DivisionChief');

        // Server-side check: does this user have any OCD Approved (unrated) vehicle requests?
        $hasPendingCsm = VehicleRequest::where('requestor_id', $user->id)
            ->where('status', 'OCD Approved')
            ->exists();

        return Inertia::render('VehicleRequests/Index', [
            'requests'        => $requests,
            'vehicles'        => $vehicles,
            'isDivisionChief' => $isDivisionChief,
            'hasPendingCsm'   => $hasPendingCsm,
            'hasPin'          => ! empty($user->signature_pin),
            'signatureUri'    => $this->sigService->getSignatureDataUri($user),
        ]);
    }

    /**
     * Show the form for creating a new vehicle request.
     */
    public function create()
    {
        return redirect()->route('vehicle-requests.index');
    }

    /**
     * Store a new vehicle request
     */
    public function store(Request $request)
    {
        $request->validate([
            'purpose' => 'required|string|max:255',
                'destination' => 'required|string|max:255',
            // allow multiple dates as an array of dates
                'date_needed' => 'required|array|min:1',
            'date_needed.*' => 'date',
                'time_of_departure' => 'required|date_format:H:i',
                'eta' => 'required|date_format:H:i',
                'vehicle_type' => 'nullable|string|max:255',
                'passengers' => 'required|integer|min:1',
        ]);

        if ($request->input('time_of_departure') >= $request->input('eta')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'eta' => 'Arrival time must be later than the departure time.',
            ]);
        }

        $user = $request->user();

        // prepare dates array
        $dates = is_array($request->input('date_needed')) ? $request->input('date_needed') : ($request->input('date_needed') ? [$request->input('date_needed')] : []);

        // conflict detection: ensure the requested vehicle isn't already booked for any of the requested dates/time
        $vehicleName = $request->input('vehicle_type');
        $timeStart = $request->input('time_of_departure');
        $timeEnd = $request->input('eta');
        // Note: vehicle_type submitted here is only a *preference* hint — the
        // actual vehicle (and driver) are assigned later by GSU during dispatch,
        // so no booking-conflict check is performed against it at this stage.
        // GSU's dispatch step performs the authoritative conflict check.

        // Auto-resolve the requestor's Division Chief (same pattern as IT Job Requests)
        $chiefId = $user->division_id
            ? \App\Models\Division::where('id', $user->division_id)->value('division_chief_id')
            : null;

        if (! $chiefId) {
            return back()->withErrors(['division_chief_id' => 'You are not assigned to a division with a Division Chief. Please contact HR.']);
        }

        $vr = VehicleRequest::create([
            'requestor_id' => $user->id,
            'purpose' => $request->input('purpose'),
            'destination' => $request->input('destination'),
            // store first date in legacy `date_needed` and full array in `date_needed_multiple`
            'date_needed' => $dates[0] ?? null,
            'date_needed_multiple' => $dates,
            'time_of_departure' => $request->input('time_of_departure'),
            'eta' => $request->input('eta'),
            'vehicle_type' => $request->input('vehicle_type'),
            'passengers' => $request->input('passengers') ?? 1,
            'division_chief_id' => $chiefId,
            'status' => 'Pending GSU Assignment',
        ]);

        // Notify GSU Head / GSU Dispatcher users so they can assign a driver and vehicle
        $gsuUsers = User::havingAnyRole(['GSU Head', 'GSU Dispatcher'])->get();
        foreach ($gsuUsers as $gsuUser) {
            if ($gsuUser->email) {
                try {
                    Mail::to($gsuUser->email)->send(new \App\Mail\VehicleRequestGSUHeadMail($vr));
                } catch (\Throwable $e) {
                    logger()->error('Failed to send GSU dispatch notification email', ['error' => $e->getMessage()]);
                }
            }
            NotificationService::notifyUser($gsuUser, 'Vehicle Request', "#{$vr->id}", 'New request awaiting driver/vehicle assignment', route('vehicle-requests.gsu-dispatch'));
        }

        $this->performSign($request, VehicleRequest::class, $vr->id,
            'submission',
            "Vehicle Request #{$vr->id}",
            VehicleRequest::class . $vr->id . 'submission'
        );

        return redirect()->route('vehicle-requests.index');
    }

    // Authenticated in-app approval by logged-in Division Chief
    public function approveInApp(Request $request, VehicleRequest $vehicleRequest)
    {
        $user = $request->user();
        logger()->info('approveInApp called', ['user_id' => $user->id ?? null, 'role' => $user->getRoleName(), 'vehicle_request_id' => $vehicleRequest->id]);

        if (! $user || ! $user->hasPermission('vehicles.dc-approve')) {
            logger()->warning('approveInApp forbidden - not division chief', ['user_id' => $user->id ?? null]);
            return back()->withErrors(['message' => 'You are not authorized to approve this request.']);
        }

        // Allow if assigned chief OR if the acting user is the chief of the requester's division
        $canAct = false;
        if ($vehicleRequest->division_chief_id && (int) $vehicleRequest->division_chief_id === (int) $user->id) {
            $canAct = true;
        } else {
            $requester = $vehicleRequest->requester;
            if ($requester && $requester->division_id) {
                $isChiefOfRequesterDivision = \App\Models\Division::where('id', $requester->division_id)
                    ->where('division_chief_id', $user->id)
                    ->exists();
                if ($isChiefOfRequesterDivision) $canAct = true;
            }
        }

        if (! $canAct) {
            logger()->warning('approveInApp forbidden - not assigned nor chief of requester division', ['vehicle_request_id' => $vehicleRequest->id, 'assigned_chief' => $vehicleRequest->division_chief_id ?? null, 'user_id' => $user->id]);
            return back()->withErrors(['message' => 'You are not authorized to approve this request.']);
        }

        if ($vehicleRequest->status === 'Approved') {
            return back()->withErrors(['message' => 'This request has already been approved.']);
        }

        $vehicleRequest->status = 'Approved';
        $vehicleRequest->save();
        if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Approved by Division Chief', route('vehicle-requests.index')); }

        $this->snapshots->recordApproval(
            approvable: $vehicleRequest,
            step:       ApprovalStep::REQ_DIVISION_CHIEF,
            sequence:   1,
            action:     'approved',
            approver:   $user,
        );

        try {
            // Driver + vehicle were already assigned by GSU before this DC approval,
            // so notify the final approver directly for sign-off instead of routing
            // back to GSU — OCD normally, FAD Chief in OED (no campus director).
            $ocdUsers = \App\Models\User::havingRole(ApprovalRoutingService::finalApproverRole())->get();
            foreach ($ocdUsers as $ocdUser) {
                if ($ocdUser->email) {
                    try {
                        $approveUrl = URL::signedRoute('vehicle-requests.ocd.approve', ['vehicleRequest' => $vehicleRequest->id, 'ocd' => $ocdUser->id], now()->addDays(7));
                        $declineUrl = URL::signedRoute('vehicle-requests.ocd.decline', ['vehicleRequest' => $vehicleRequest->id, 'ocd' => $ocdUser->id], now()->addDays(7));
                        Mail::to($ocdUser->email)->send(new \App\Mail\VehicleRequestOCDMail($vehicleRequest, $approveUrl, $declineUrl));
                    } catch (\Throwable $e) {
                        logger()->error('Failed to send OCD vehicle request notification (in-app)', ['error' => $e->getMessage(), 'ocd_id' => $ocdUser->id]);
                    }
                }
                NotificationService::notifyUser($ocdUser, 'Vehicle Request', "#{$vehicleRequest->id}", 'Approved by Division Chief — awaiting your approval', route('vehicle-requests.ocd-approval'));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to notify OCD users after in-app approval', ['error' => $e->getMessage()]);
        }

        $this->performSign($request, VehicleRequest::class, $vehicleRequest->id,
            'dc_approval',
            "Vehicle Request #{$vehicleRequest->id}",
            VehicleRequest::class . $vehicleRequest->id . 'dc_approval'
        );

        return back()->with('success', 'Vehicle request approved.');
    }

    public function declineInApp(Request $request, VehicleRequest $vehicleRequest)
    {
        $user = $request->user();
        logger()->info('declineInApp called', ['user_id' => $user->id ?? null, 'role' => $user->getRoleName(), 'vehicle_request_id' => $vehicleRequest->id]);

        if (! $user || ! $user->hasPermission('vehicles.dc-approve')) {
            logger()->warning('declineInApp forbidden - not division chief', ['user_id' => $user->id ?? null]);
            return back()->withErrors(['message' => 'You are not authorized to decline this request.']);
        }

        $canAct = false;
        if ($vehicleRequest->division_chief_id && (int) $vehicleRequest->division_chief_id === (int) $user->id) {
            $canAct = true;
        } else {
            $requester = $vehicleRequest->requester;
            if ($requester && $requester->division_id) {
                $isChiefOfRequesterDivision = \App\Models\Division::where('id', $requester->division_id)
                    ->where('division_chief_id', $user->id)
                    ->exists();
                if ($isChiefOfRequesterDivision) $canAct = true;
            }
        }

        if (! $canAct) {
            logger()->warning('declineInApp forbidden - not assigned nor chief of requester division', ['vehicle_request_id' => $vehicleRequest->id, 'assigned_chief' => $vehicleRequest->division_chief_id ?? null, 'user_id' => $user->id]);
            return back()->withErrors(['message' => 'You are not authorized to decline this request.']);
        }

        $data = $request->validate(['reason' => 'required|string|max:1000']);

        if (in_array($vehicleRequest->status, ['Approved','Declined'])) {
            return back()->withErrors(['message' => 'This request has already been processed.']);
        }

        $vehicleRequest->status = 'Declined';
        $vehicleRequest->decline_reason = $data['reason'];
        $vehicleRequest->declined_at = now();
        $vehicleRequest->save();
        if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Rejected by Division Chief', route('vehicle-requests.index')); }

        $this->snapshots->recordApproval(
            approvable: $vehicleRequest,
            step:       ApprovalStep::REQ_DIVISION_CHIEF,
            sequence:   1,
            action:     'rejected',
            approver:   $user,
        );

        try {
                $requester = $vehicleRequest->requester;
            $requesterEmail = $requester?->email ?? null;
            if ($requesterEmail) {
                Mail::to($requesterEmail)->send(new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'Declined', $vehicleRequest->decline_reason ?? null));
            }
        } catch (\Throwable $e) {
            logger()->error('Failed to send vehicle request declined notification', ['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Vehicle request declined.');
    }

    /**
     * Approve vehicle request by Division Chief via signed link
     */
    public function approveByDivisionChief(Request $request, VehicleRequest $vehicleRequest, $chief)
    {
        // Signed route ensures URL integrity. Verify that the chief in the link matches the assigned approver.
        if ($vehicleRequest->division_chief_id && (int) $vehicleRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        // If already approved, show a friendly message and do not change state.
        if ($vehicleRequest->status === 'Approved') {
            return view('vehicle_request_approved', ['vehicleRequest' => $vehicleRequest, 'already' => true]);
        }

        $vehicleRequest->status = 'Approved';
        $vehicleRequest->save();
        if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Approved by Division Chief', route('vehicle-requests.index')); }

        // Driver + vehicle were already assigned by GSU before this Division Chief
        // approval, so notify the final approver directly for sign-off — OCD
        // normally, FAD Chief in OED (no campus director).
        $ocdUsers = \App\Models\User::havingRole(ApprovalRoutingService::finalApproverRole())->get();
        foreach ($ocdUsers as $ocdUser) {
            if ($ocdUser->email) {
                try {
                    $approveUrl = URL::signedRoute('vehicle-requests.ocd.approve', ['vehicleRequest' => $vehicleRequest->id, 'ocd' => $ocdUser->id], now()->addDays(7));
                    $declineUrl = URL::signedRoute('vehicle-requests.ocd.decline', ['vehicleRequest' => $vehicleRequest->id, 'ocd' => $ocdUser->id], now()->addDays(7));
                    \Mail::to($ocdUser->email)->send(new \App\Mail\VehicleRequestOCDMail($vehicleRequest, $approveUrl, $declineUrl));
                } catch (\Throwable $e) {
                    \Log::error('Failed to send OCD vehicle request notification', ['error' => $e->getMessage()]);
                }
            }
            NotificationService::notifyUser($ocdUser, 'Vehicle Request', "#{$vehicleRequest->id}", 'Approved by Division Chief — awaiting your approval', route('vehicle-requests.ocd-approval'));
        }

        return view('vehicle_request_approved', ['vehicleRequest' => $vehicleRequest, 'already' => false]);
    }

    /**
     * Show decline form for signed decline link
     */
    public function showDeclineForm(Request $request, VehicleRequest $vehicleRequest, $chief)
    {
        // Ensure the chief matches the assigned approver
        if ($vehicleRequest->division_chief_id && (int) $vehicleRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        // If already approved, show the approved page
        if ($vehicleRequest->status === 'Approved') {
            return view('vehicle_request_approved', ['vehicleRequest' => $vehicleRequest, 'already' => true]);
        }

        // Build POST action preserving signature query parameters so signed middleware validates POST as well
        $postAction = route('vehicle-requests.decline.submit', ['vehicleRequest' => $vehicleRequest->id, 'chief' => $chief])
            . '?' . $request->getQueryString();

        return view('vehicle_request_decline', ['vehicleRequest' => $vehicleRequest, 'postAction' => $postAction]);
    }

    /**
     * OCD approve via signed link
     */
    public function approveByOCD(Request $request, VehicleRequest $vehicleRequest, $ocd)
    {
        // Signed route ensures integrity. No further role check here, as it's validated by signature.
        if ($vehicleRequest->status === 'OCD Approved') {
            return view('vehicle_request_approved', ['vehicleRequest' => $vehicleRequest, 'already' => true]);
        }

        $vehicleRequest->status = 'OCD Approved';
        $vehicleRequest->save();
        if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Fully Approved — request scheduled', route('vehicle-requests.index')); }

        // Notify requester
        $requester = $vehicleRequest->requester;
        if ($requester && $requester->email) {
            try {
                \Mail::to($requester->email)->send(new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'OCD Approved', null, 'Office of the Campus Director'));
            } catch (\Throwable $e) {
                \Log::error('Failed to send vehicle request OCD approved notification', ['error' => $e->getMessage()]);
            }
        }

        return view('vehicle_request_approved', ['vehicleRequest' => $vehicleRequest, 'already' => false]);
    }

    public function showOcdDeclineForm(Request $request, VehicleRequest $vehicleRequest, $ocd)
    {
        // If already approved, show approved page
        if ($vehicleRequest->status === 'Approved' || $vehicleRequest->status === 'OCD Approved') {
            return view('vehicle_request_approved', ['vehicleRequest' => $vehicleRequest, 'already' => true]);
        }

        $postAction = route('vehicle-requests.ocd.decline.submit', ['vehicleRequest' => $vehicleRequest->id, 'ocd' => $ocd])
            . '?' . $request->getQueryString();

        return view('vehicle_request_decline', ['vehicleRequest' => $vehicleRequest, 'postAction' => $postAction]);
    }

    public function submitOcdDecline(Request $request, VehicleRequest $vehicleRequest, $ocd)
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        if (in_array($vehicleRequest->status, ['Approved','Declined','OCD Approved'])) {
            $reason = $vehicleRequest->decline_reason ?? '—';
            return view('vehicle_request_declined', ['vehicleRequest' => $vehicleRequest, 'reason' => $reason]);
        }

        $vehicleRequest->status = 'Declined';
        $vehicleRequest->decline_reason = $request->input('reason');
        $vehicleRequest->declined_at = now();
        $vehicleRequest->save();
        if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Declined by OCD', route('vehicle-requests.index')); }

        // Notify requester
        $requester = $vehicleRequest->requester;
        if ($requester && $requester->email) {
            try {
                \Mail::to($requester->email)->send(new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'Declined', $vehicleRequest->decline_reason, 'Office of the Campus Director'));
            } catch (\Throwable $e) {
                \Log::error('Failed to send vehicle request declined notification', ['error' => $e->getMessage()]);
            }
        }

        return view('vehicle_request_declined', ['vehicleRequest' => $vehicleRequest, 'reason' => $vehicleRequest->decline_reason]);
    }

    /**
     * Handle decline submission
     */
    public function submitDecline(Request $request, VehicleRequest $vehicleRequest, $chief)
    {
        // Ensure the chief matches the assigned approver
        if ($vehicleRequest->division_chief_id && (int) $vehicleRequest->division_chief_id !== (int) $chief) {
            abort(403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        // If already processed
        if (in_array($vehicleRequest->status, ['Approved','Declined'])) {
            $reason = $vehicleRequest->decline_reason ?? '—';
            return view('vehicle_request_declined', ['vehicleRequest' => $vehicleRequest, 'reason' => $reason]);
        }

        $vehicleRequest->status = 'Declined';
        $vehicleRequest->decline_reason = $request->input('reason');
        $vehicleRequest->declined_at = now();
        $vehicleRequest->save();
        if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Rejected by Division Chief', route('vehicle-requests.index')); }

        // Notify requester via email (declined by Division Chief)
        $requester = $vehicleRequest->requester;
        if ($requester && $requester->email) {
            try {
                \Mail::to($requester->email)->send(new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'Declined', $vehicleRequest->decline_reason, 'Division Chief'));
            } catch (\Throwable $e) {
                \Log::error('Failed to send vehicle request declined notification', ['error' => $e->getMessage()]);
            }
        }

        return view('vehicle_request_declined', ['vehicleRequest' => $vehicleRequest, 'reason' => $vehicleRequest->decline_reason]);
    }

    /**
     * Update the specified vehicle request (admin only)
     */
    public function update(Request $request, VehicleRequest $vehicleRequest)
    {
        $isAdmin = $request->user()->isSuperAdmin();
        if (! $isAdmin) {
            abort(403);
        }


        $request->validate([
            'purpose' => 'required|string|max:255',
            'destination' => 'required|string|max:255',
            'date_needed' => 'required|array|min:1',
            'date_needed.*' => 'date',
            'time_of_departure' => 'required|date_format:H:i',
            'eta' => 'required|date_format:H:i',
            'vehicle_type' => 'nullable|string|max:255',
            'passengers' => 'required|integer|min:1',
            'status' => 'nullable|string|max:255',
            'division_chief_id' => 'nullable|exists:users,id',
        ]);

        if ($request->input('time_of_departure') >= $request->input('eta')) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'eta' => 'Arrival time must be later than the departure time.',
            ]);
        }

        // Update both legacy single date and the new multiple dates json column
        $data = $request->only(['purpose','destination','vehicle_type','passengers','status','division_chief_id']);
        $dates = $request->input('date_needed');
        if (is_array($dates)) {
            $data['date_needed_multiple'] = $dates;
            $data['date_needed'] = $dates[0] ?? null;
        } else {
            $data['date_needed'] = $request->input('date_needed');
        }
        $data['time_of_departure'] = $request->input('time_of_departure');
        $data['eta'] = $request->input('eta');

        $vehicleRequest->update($data);

        return redirect()->route('vehicle-requests.index');
    }

    /**
     * Remove the specified vehicle request (admin only)
     */
    public function destroy(VehicleRequest $vehicleRequest)
    {
        $isAdmin = auth()->user()->isSuperAdmin();
        if (! $isAdmin) {
            abort(403);
        }
        $vehicleRequest->delete();
        return redirect()->route('vehicle-requests.index');
    }

    /**
     * Return JSON bookings for calendar display.
     * Includes both 'Approved' and 'OCD Approved' requests.
     */
    public function bookings(Request $request)
    {
        $rows = VehicleRequest::whereIn('status', ['Approved', 'OCD Approved'])
            ->with(['user'])
            ->get();

        $out = [];
        foreach ($rows as $r) {
            $dates = [];
            if ($r->date_needed_multiple && is_array($r->date_needed_multiple) && count($r->date_needed_multiple) > 0) {
                $dates = $r->date_needed_multiple;
            } elseif ($r->date_needed) {
                $dates = [$r->date_needed];
            }
            foreach ($dates as $d) {
                $out[] = [
                    'id' => $r->id,
                    'vehicle_name' => $r->vehicle_type,
                    'plate_no' => optional(\App\Models\Vehicle::where('name', $r->vehicle_type)->first())->plate_no ?? null,
                    'date' => $d,
                    'start_time' => $r->time_of_departure,
                    'end_time' => $r->eta,
                    'purpose' => $r->purpose,
                    'status' => $r->status,
                ];
            }
        }

        return response()->json($out);
    }

    /**
     * Show a printable trip ticket (HTML) for a vehicle request.
     * Only accessible to Admin and GSU Head via route middleware.
     */
    public function printTicket(Request $request, VehicleRequest $vehicleRequest)
    {
        $user = $request->user();

        if (! $user->hasPermission('vehicles.manage')) {
            abort(403);
        }

        // Allow printing once OCD has approved (includes Completed requests)
        if (! in_array($vehicleRequest->status, ['OCD Approved', 'Completed'])) {
            abort(403, 'Request not ready for printing');
        }

        $vehicleRequest->load(['requester','driver', 'divisionChief']);

        $director    = User::havingRole(ApprovalRoutingService::finalApproverRole())->first();
        $directorSig = $this->sigDataUri($director?->electronic_signature);

        $sigs = $this->loadSigsForPrint(VehicleRequest::class, $vehicleRequest->id);

        // Document-level verification QR — signed URL carries the campus so
        // anonymous scans resolve the right tenant (like the ITJR PDF).
        $verifyUrl  = \Illuminate\Support\Facades\URL::signedRoute('request.verify', ['type' => 'vehicle', 'id' => $vehicleRequest->id]);
        $documentQr = ! empty($sigs)
            ? base64_encode(\SimpleSoftwareIO\QrCode\Facades\QrCode::format('svg')->size(120)->margin(1)->generate($verifyUrl))
            : null;

        return view('vehicle_requests.print_ticket', [
            'request'     => $vehicleRequest,
            'director'    => $director,
            'directorSig' => $directorSig,
            'sigs'        => $sigs,
            'documentQr'  => $documentQr,
            'verifyUrl'   => $verifyUrl,
        ]);
    }

    private function sigDataUri(?string $storedPath): ?string
    {
        if (! $storedPath || $storedPath === '0') {
            return null;
        }

        $ext  = strtolower(pathinfo($storedPath, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };

        foreach (['s3', 'public'] as $disk) {
            try {
                if (Storage::disk($disk)->exists($storedPath)) {
                    $contents = Storage::disk($disk)->get($storedPath);
                    if ($contents) {
                        return 'data:' . $mime . ';base64,' . base64_encode($contents);
                    }
                }
            } catch (\Throwable $e) {
                logger()->warning("VehicleRequestController: sig load failed on {$disk}", [
                    'path' => $storedPath, 'error' => $e->getMessage(),
                ]);
            }
        }

        return null;
    }

    /* =====================================================
     | DIVISION CHIEF IN-APP APPROVAL DASHBOARD
     |=====================================================*/
    public function divisionChiefApproval(Request $request)
    {
        $user = $request->user();
        if (! $user->hasPermission('vehicles.dc-approve')) {
            abort(403);
        }

        $search  = trim($request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $requests = VehicleRequest::with('requester:id,name')
            ->where('status', 'Pending Division Chief Approval')
            ->where('division_chief_id', $user->id)
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('purpose',     'like', "%{$search}%")
                      ->orWhere('destination', 'like', "%{$search}%")
                      ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('VehicleRequests/DivisionChiefApproval', [
            'requests'     => $requests,
            'filters'      => ['search' => $search],
            'hasPin'       => ! empty($user->signature_pin),
            'signatureUri' => $this->sigService->getSignatureDataUri($user),
        ]);
    }

    /* =====================================================
     | GSU DISPATCH DASHBOARD (GSU Head / GSU Dispatcher)
     |=====================================================*/
    public function gsuDispatch(Request $request)
    {
        $user = $request->user();
        if (! $user->hasPermission('vehicles.dispatch')) {
            abort(403);
        }

        $search  = trim($request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $requests = VehicleRequest::with('requester:id,name')
            ->where('status', 'Pending GSU Assignment')
            ->when($search, fn ($q) => $q->where(function ($inner) use ($search) {
                $inner->where('purpose',     'like', "%{$search}%")
                      ->orWhere('destination', 'like', "%{$search}%")
                      ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        $drivers  = User::where('position', 'LIKE', '%Driver%')->orderBy('name')->get(['id', 'name', 'position']);
        $vehicles = \App\Models\Vehicle::where('status', '!=', 'Under Repair')->orderBy('name')->get();

        return Inertia::render('VehicleRequests/GSUDispatch', [
            'requests' => $requests,
            'filters'  => ['search' => $search],
            'drivers'  => $drivers,
            'vehicles' => $vehicles,
        ]);
    }

    /* =====================================================
     | OCD IN-APP APPROVAL DASHBOARD
     |=====================================================*/
    public function ocdApproval(Request $request)
    {
        $search  = trim($request->query('search', ''));
        $perPage = min((int) $request->query('per_page', 15), 50);

        $requests = VehicleRequest::with('requester:id,name')
            ->where('status', 'Approved')
            ->when($search, fn($q) => $q->where(function ($inner) use ($search) {
                $inner->where('purpose',     'like', "%{$search}%")
                      ->orWhere('destination','like', "%{$search}%")
                      ->orWhereHas('requester', fn($u) => $u->where('name', 'like', "%{$search}%"));
            }))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('VehicleRequests/OCDApproval', [
            'requests'      => $requests,
            'filters'       => ['search' => $search],
            'hasPin'        => ! empty($request->user()->signature_pin),
            'signatureUri'  => $this->sigService->getSignatureDataUri($request->user()),
            // 'OCD' normally, 'FAD Chief' in OED — the page can't rely on the
            // generic roleLabel() text substitution here, since OED's OCD
            // role is separately labelled "KID Chief" for its ITJR duty and
            // that label would be wrong on this now-FAD-Chief-owned page.
            'approverLabel' => ApprovalRoutingService::finalApproverRole(),
        ]);
    }

    public function approveByOCDInApp(Request $request, VehicleRequest $vehicleRequest)
    {
        $request->validate(['action' => 'required|in:approve,reject']);

        if (in_array($vehicleRequest->status, ['OCD Approved', 'Declined'], true)) {
            return back()->withErrors(['message' => 'This request has already been acted upon.']);
        }

        if ($request->action === 'approve') {
            $vehicleRequest->update(['status' => 'OCD Approved']);
            if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Fully Approved — request scheduled', route('vehicle-requests.index')); }

            $this->snapshots->recordApproval(
                approvable: $vehicleRequest,
                step:       ApprovalStep::REQ_OCD,
                sequence:   4,
                action:     'approved',
                approver:   $request->user(),
            );

            $this->performSign($request, VehicleRequest::class, $vehicleRequest->id,
                'ocd_approval',
                "Vehicle Request #{$vehicleRequest->id}",
                VehicleRequest::class . $vehicleRequest->id . 'ocd_approval'
            );

            try {
                $requester = $vehicleRequest->requester;
                if ($requester?->email) {
                    \Mail::to($requester->email)->send(
                        new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'OCD Approved', null, $request->user()->name)
                    );
                }
            } catch (\Throwable $e) {
                logger()->error('Vehicle OCD approved email failed', ['error' => $e->getMessage()]);
            }
        } else {
            $vehicleRequest->update(['status' => 'Declined', 'decline_reason' => 'Declined by OCD.']);
            if ($vehicleRequest->requester) { NotificationService::notifyUser($vehicleRequest->requester, 'Vehicle Request', "#{$vehicleRequest->id}", 'Declined by OCD', route('vehicle-requests.index')); }

            $this->snapshots->recordApproval(
                approvable: $vehicleRequest,
                step:       ApprovalStep::REQ_OCD,
                sequence:   4,
                action:     'rejected',
                approver:   $request->user(),
            );

            try {
                $requester = $vehicleRequest->requester;
                if ($requester?->email) {
                    \Mail::to($requester->email)->send(
                        new \App\Mail\VehicleRequestStatusMail($vehicleRequest, 'Declined', 'Declined by OCD.', $request->user()->name)
                    );
                }
            } catch (\Throwable $e) {
                logger()->error('Vehicle OCD declined email failed', ['error' => $e->getMessage()]);
            }
        }

        return back()->with('success', 'OCD action recorded.');
    }

    public function signCompletion(Request $request, VehicleRequest $vehicleRequest)
    {
        if ($vehicleRequest->requestor_id !== $request->user()->id) {
            abort(403);
        }

        $this->performSign(
            $request,
            VehicleRequest::class,
            $vehicleRequest->id,
            'completion',
            "Vehicle Request #{$vehicleRequest->id}",
            VehicleRequest::class . $vehicleRequest->id . 'completion'
        );

        return response()->json(['ok' => true]);
    }
}
