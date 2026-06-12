<?php

use App\Http\Controllers\ApprovalInboxController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\FacilityController;
use App\Http\Controllers\ICTEquipmentController;
use App\Http\Controllers\ICTPMSHistoryController;
use App\Http\Controllers\ITJobRequestController;
use App\Http\Controllers\PMSController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\VehicleRequestController;
use App\Http\Controllers\WorkRequestController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| SRMIS Tenant Routes
|--------------------------------------------------------------------------
|
| Every route in this file runs inside an initialized tenant context —
| the tenant (campus) is resolved from the subdomain before any of these
| routes execute, and all DB/cache/storage operations are scoped to that
| tenant's schema.
|
| Modules: MIS, General Services, Data Management, Approval Inbox,
|          Reports, Chat, Digital Signature.
|
*/

// ── Public ────────────────────────────────────────────────────────────────────

// Google login (Firebase popup) + Socialite OAuth (server-side)
Route::post('/google/login', [GoogleAuthController::class, 'login'])->name('google.login');
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

// System health check (auth required — internal monitoring only)
Route::get('/_status', [\App\Http\Controllers\HealthController::class, 'check'])
    ->middleware(['auth', 'throttle:30,1'])
    ->name('system.health');

// ── Document / signature verification — public QR landing pages ──────────────
Route::get('/verify/{token}', [\App\Http\Controllers\DocumentVerificationController::class, 'show'])
    ->name('document.verify')
    ->where('token', '[0-9a-f\-]{36}');
Route::get('/verify/itjr/{itjrNo}', [\App\Http\Controllers\DocumentVerificationController::class, 'showItjr'])
    ->name('itjr.verify')
    ->where('itjrNo', '[0-9\-]+');

// ── IT Job Requests — signed email-link approvals (no session required) ──────
Route::prefix('it-job-requests')->group(function () {
    Route::get('/for-approval', [ITJobRequestController::class, 'forApproval'])
        ->name('job-requests.for-approval')
        ->middleware(['auth', 'permission:it.requests.manage']);

    Route::get('dc/approve/{jobRequest}/{chief}', [ITJobRequestController::class, 'approveByDivisionChiefSigned'])
        ->name('it-job-requests.dc.approve')->middleware('signed');
    Route::get('dc/decline/{jobRequest}/{chief}', [ITJobRequestController::class, 'showDivisionChiefDeclineForm'])
        ->name('it-job-requests.dc.decline')->middleware('signed');
    Route::post('dc/decline/{jobRequest}/{chief}', [ITJobRequestController::class, 'submitDivisionChiefDecline'])
        ->name('it-job-requests.dc.decline.submit')->middleware('signed');

    Route::get('ocd/approve/{jobRequest}/{ocd}', [ITJobRequestController::class, 'approveByOCDSigned'])
        ->name('it-job-requests.ocd.approve')->middleware('signed');
    Route::get('ocd/decline/{jobRequest}/{ocd}', [ITJobRequestController::class, 'showOCDDeclineForm'])
        ->name('it-job-requests.ocd.decline')->middleware('signed');
    Route::post('ocd/decline/{jobRequest}/{ocd}', [ITJobRequestController::class, 'submitOCDDecline'])
        ->name('it-job-requests.ocd.decline.submit')->middleware('signed');
});

// ── Web Push subscriptions ────────────────────────────────────────────────────
Route::get('/api/push-subscriptions/vapid-public-key', [\App\Http\Controllers\PushSubscriptionController::class, 'vapidPublicKey'])->name('push.vapid-key');
Route::middleware(['auth'])->group(function () {
    Route::post('/api/push-subscriptions',   [\App\Http\Controllers\PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/api/push-subscriptions', [\App\Http\Controllers\PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');
});

// ── In-app notifications ──────────────────────────────────────────────────────
Route::middleware(['auth'])->prefix('api/notifications')->group(function () {
    Route::get('/',           [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/{id}/read', [\App\Http\Controllers\NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/read-all',  [\App\Http\Controllers\NotificationController::class, 'markAllRead'])->name('notifications.read-all');
});

// Storage proxy — serves private S3 files through the app (auth required)
Route::middleware(['auth'])->get('/media/{path}', [\App\Http\Controllers\StorageProxyController::class, 'serve'])
    ->where('path', '.+')
    ->name('storage.proxy');

/*
|--------------------------------------------------------------------------
| Data Management
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:roles.assign'])->group(function () {
    // Offices
    Route::get('/data-management/offices', [App\Http\Controllers\OfficeController::class, 'index'])->name('offices.index');
    Route::post('/data-management/offices', [App\Http\Controllers\OfficeController::class, 'store'])->name('offices.store');
    Route::put('/data-management/offices/{office}', [App\Http\Controllers\OfficeController::class, 'update'])->name('offices.update');
    Route::delete('/data-management/offices/{office}', [App\Http\Controllers\OfficeController::class, 'destroy'])->name('offices.destroy');
    // Buildings
    Route::get('/data-management/buildings', [App\Http\Controllers\BuildingController::class, 'index'])->name('buildings.index');
    Route::post('/data-management/buildings', [App\Http\Controllers\BuildingController::class, 'store'])->name('buildings.store');
    Route::put('/data-management/buildings/{building}', [App\Http\Controllers\BuildingController::class, 'update'])->name('buildings.update');
    Route::delete('/data-management/buildings/{building}', [App\Http\Controllers\BuildingController::class, 'destroy'])->name('buildings.destroy');
    // Rooms
    Route::get('/data-management/rooms', [App\Http\Controllers\RoomController::class, 'index'])->name('rooms.index');
    Route::post('/data-management/rooms', [App\Http\Controllers\RoomController::class, 'store'])->name('rooms.store');
    Route::put('/data-management/rooms/{room}', [App\Http\Controllers\RoomController::class, 'update'])->name('rooms.update');
    Route::delete('/data-management/rooms/{room}', [App\Http\Controllers\RoomController::class, 'destroy'])->name('rooms.destroy');
    // Campuses
    Route::get('/data-management/campuses', [App\Http\Controllers\DataManagement\CampusController::class, 'index'])->name('campuses.index');
    Route::post('/data-management/campuses', [App\Http\Controllers\DataManagement\CampusController::class, 'store'])->name('campuses.store');
    Route::put('/data-management/campuses/{campus}', [App\Http\Controllers\DataManagement\CampusController::class, 'update'])->name('campuses.update');
    Route::delete('/data-management/campuses/{campus}', [App\Http\Controllers\DataManagement\CampusController::class, 'destroy'])->name('campuses.destroy');
});

/*
|--------------------------------------------------------------------------
| Authenticated Routes (allowed email domain only)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'allowed.domain'])->group(function () {

    Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
        ->middleware(['verified'])
        ->name('dashboard');

    // ── Data Privacy Policy ───────────────────────────────────────────────────
    Route::get('/privacy', fn () => inertia('Privacy/Index'))->name('privacy.index');

    // ── Unified Approvals Inbox ───────────────────────────────────────────────
    Route::get('/inbox', [ApprovalInboxController::class, 'index'])->name('approvals.inbox');
    Route::post('/inbox/{type}/{id}/approve', [ApprovalInboxController::class, 'approve'])->name('approvals.approve');
    Route::post('/inbox/{type}/{id}/decline', [ApprovalInboxController::class, 'decline'])->name('approvals.decline');

    /*
    |--------------------------------------------------------------------------
    | MIS — CSM Feedback, MIS Dashboard, IT Job Requests, ICT Equipment, PMS
    |--------------------------------------------------------------------------
    */
    // Feedback submission — any authenticated user
    Route::post('/csm', [\App\Http\Controllers\CsmResponseController::class, 'store'])->name('csm.store');

    // CSM Feedback Center (admin/MIS only)
    Route::middleware('permission:it.requests.manage')->group(function () {
        Route::get('/csm/dashboard', [\App\Http\Controllers\CSMFeedbackController::class, 'dashboard'])->name('csm.dashboard');
        Route::get('/csm/list',      [\App\Http\Controllers\CSMFeedbackController::class, 'index'])->name('csm.list');
        Route::get('/csm/list/{csmResponse}', [\App\Http\Controllers\CSMFeedbackController::class, 'show'])->name('csm.show');
        Route::get('/csm/export',    [\App\Http\Controllers\CSMFeedbackController::class, 'export'])->name('csm.export');
    });

    Route::get('/mis/dashboard', [\App\Http\Controllers\MISDashboardController::class, 'index'])
        ->middleware('permission:it.requests.manage')
        ->name('mis.dashboard');

    // IT Job Requests
    Route::get('/job-requests/check-pending-itjr', [ITJobRequestController::class, 'checkPendingActedByMis'])->name('jobrequests.check-pending');
    Route::get('/job-requests/export-pdf', [ITJobRequestController::class, 'exportPdf'])->name('jobrequests.export-pdf');
    Route::get('/job-requests/queue', [ITJobRequestController::class, 'queue'])->name('jobrequests.queue');
    Route::put('/job-requests/{jobRequest}/priority', [ITJobRequestController::class, 'updatePriority'])->name('jobrequests.update-priority')->middleware('permission:it.requests.manage');
    Route::get('/job-requests', [ITJobRequestController::class, 'index'])->name('jobrequests.index');
    Route::get('/job-requests/create', [ITJobRequestController::class, 'create'])->name('jobrequests.create');
    Route::post('/job-requests', [ITJobRequestController::class, 'store'])->name('jobrequests.store');
    Route::delete('/job-requests/{jobRequest}', [ITJobRequestController::class, 'destroy'])->name('jobrequests.destroy');
    Route::get('/job-requests/{jobRequest}/print', [ITJobRequestController::class, 'printForm'])->name('jobrequests.print');
    Route::post('/it-job-requests/{jobRequest}/confirm', [ITJobRequestController::class, 'confirmCompletion']);
    Route::post('/it-job-requests/{jobRequest}/sign-completion', [ITJobRequestController::class, 'signCompletion'])->name('jobrequests.sign-completion');
    Route::get('/itjr/{jobRequest}/division-chief/{action}', [ITJobRequestController::class, 'approveByDivisionChief'])->name('itjr.dc.action');

    Route::post('/job-requests/{jobRequest}/assess', [ITJobRequestController::class, 'assess'])
        ->middleware('permission:it.requests.manage')
        ->name('jobrequests.assess');
    Route::put('/job-requests/{itJobRequest}/update', [ITJobRequestController::class, 'update'])
        ->name('job-requests.update');

    // ITJR in-app approval dashboards (Division Chief / OCD)
    Route::middleware('permission:it.requests.manage')->group(function () {
        Route::get('/job-requests/for-approval', [ITJobRequestController::class, 'forApproval'])
            ->name('it.job-requests.for-approval');
        Route::post('/job-requests/{jobRequest}/division-chief-action', [ITJobRequestController::class, 'approveByDivisionChief'])
            ->name('job-requests.division-chief-action');
        Route::get('/job-requests/ocd-approval', [ITJobRequestController::class, 'ocdApproval'])
            ->name('job-requests.ocd-approval');
        Route::post('/job-requests/{jobRequest}/ocd-action', [ITJobRequestController::class, 'approveByOCD'])
            ->name('job-requests.ocd-action');
    });
    Route::get('/job-requests/{jobRequest}', [ITJobRequestController::class, 'show'])
        ->name('job-requests.show');

    // App versions (deployment cache-busting banner)
    Route::post('/app-versions', [\App\Http\Controllers\AppVersionController::class, 'store'])->name('app-versions.store');

    // ICT Equipment Inventory
    Route::middleware('permission:it.equipment.view')->group(function () {
        Route::get('/ict-equipments', [ICTEquipmentController::class, 'index'])->name('ict-equipments.index');
        Route::get('/ict-equipments/{id}', [ICTEquipmentController::class, 'show'])->name('ict-equipments.show');
        Route::post('/ict-equipments/report/generate', [ICTEquipmentController::class, 'generateReport'])->name('ict-equipments.report.generate');
    });
    Route::middleware('permission:it.equipment.manage')->group(function () {
        Route::post('/ict-equipments', [ICTEquipmentController::class, 'store'])->name('ict-equipments.store');
        Route::put('/ict-equipments/{ictEquipment}', [ICTEquipmentController::class, 'update'])->name('ict-equipments.update');
        Route::delete('/ict-equipments/{ictEquipment}', [ICTEquipmentController::class, 'destroy'])->name('ict-equipments.destroy');
    });
    // Public QR landing for equipment (any authenticated user)
    Route::get('/equipment/{ictEquipment}', [ICTEquipmentController::class, 'publicShow'])->name('equipment.public.show');
    Route::get('/equipment/{ictEquipment}/qr', [ICTEquipmentController::class, 'qrCode'])->name('equipment.qr');

    // ICT Preventive Maintenance Schedule (PMS)
    Route::middleware('permission:it.equipment.view')->group(function () {
        Route::get('/ict-pms', [PMSController::class, 'index'])->name('ict-pms.index');
        Route::get('/ict-pms/{id}', [PMSController::class, 'show'])->name('ict-pms.show');
        Route::get('/ict-pms/{pms}/equipments', [PMSController::class, 'showEquipments'])->name('ict-pms.show-equipments');
    });
    Route::middleware('permission:it.equipment.manage')->group(function () {
        Route::post('/ict-pms', [PMSController::class, 'store'])->name('ict-pms.store');
        Route::post('/ict-pms/{pmsId}/assign-equipments', [PMSController::class, 'assignEquipments'])->name('ict-pms.assign-equipments');
        Route::post('/ict-pms-history', [ICTPMSHistoryController::class, 'store'])->name('ict-pms-history.store');
    });

    /*
    |--------------------------------------------------------------------------
    | General Services — Vehicle / Facility / Work / Service / Messengerial
    |--------------------------------------------------------------------------
    */

    // Division Chief in-app approval dashboards
    Route::get('/vehicle-requests/dc-approval',   [VehicleRequestController::class,  'divisionChiefApproval'])->name('vehicle-requests.dc-approval')->middleware('permission:vehicles.dc-approve');
    Route::get('/facility-requests/dc-approval',  [\App\Http\Controllers\FacilityRequestController::class, 'divisionChiefApproval'])->name('facility-requests.dc-approval')->middleware('permission:facilities.dc-approve');
    Route::get('/work-requests/dc-approval',      [WorkRequestController::class,     'divisionChiefApproval'])->name('work-requests.dc-approval')->middleware('permission:facilities.dc-approve');
    Route::get('/service-requests/dc-approval',   [\App\Http\Controllers\ServiceRequestController::class,  'divisionChiefApproval'])->name('service-requests.dc-approval')->middleware('permission:facilities.dc-approve');

    // FAD in-app approval dashboards
    Route::get('/facility-requests/fad-approval',                  [\App\Http\Controllers\FacilityRequestController::class, 'fadApproval'])->name('facility-requests.fad-approval')->middleware('permission:facilities.fad-approve');
    Route::post('/facility-requests/{facilityRequest}/fad-action', [\App\Http\Controllers\FacilityRequestController::class, 'fadAction'])->name('facility-requests.fad-action')->middleware('permission:facilities.fad-approve');
    Route::get('/work-requests/fad-approval',                      [WorkRequestController::class,     'fadApproval'])->name('work-requests.fad-approval')->middleware('permission:facilities.fad-approve');
    Route::post('/work-requests/{workRequest}/fad-action',         [WorkRequestController::class,     'fadAction'])->name('work-requests.fad-action')->middleware('permission:facilities.fad-approve');
    Route::get('/service-requests/fad-approval',                   [\App\Http\Controllers\ServiceRequestController::class,  'fadApproval'])->name('service-requests.fad-approval')->middleware('permission:facilities.fad-approve');
    Route::post('/service-requests/{serviceRequest}/fad-action',   [\App\Http\Controllers\ServiceRequestController::class,  'fadAction'])->name('service-requests.fad-action')->middleware('permission:facilities.fad-approve');

    // OCD in-app approval dashboards
    Route::middleware('permission:vehicles.ocd-approve')->group(function () {
        Route::get('/vehicle-requests/ocd-approval', [VehicleRequestController::class, 'ocdApproval'])->name('vehicle-requests.ocd-approval');
        Route::post('/vehicle-requests/{vehicleRequest}/ocd-action', [VehicleRequestController::class, 'approveByOCDInApp'])->name('vehicle-requests.ocd-action');
    });
    Route::middleware('permission:facilities.ocd-approve')->group(function () {
        Route::get('/facility-requests/ocd-approval', [\App\Http\Controllers\FacilityRequestController::class, 'ocdApproval'])->name('facility-requests.ocd-approval');
        Route::post('/facility-requests/{facilityRequest}/ocd-action', [\App\Http\Controllers\FacilityRequestController::class, 'ocdAction'])->name('facility-requests.ocd-action');
    });

    // ── Vehicle Requests ──────────────────────────────────────────────────────
    Route::get('/vehicle-requests', [VehicleRequestController::class, 'index'])->name('vehicle-requests.index');
    Route::post('/vehicle-requests', [VehicleRequestController::class, 'store'])->name('vehicle-requests.store');
    Route::post('/vehicle-requests/{vehicleRequest}/approve', [VehicleRequestController::class, 'approveInApp'])->name('vehicle-requests.approve.inapp')->middleware('permission:vehicles.dc-approve');
    Route::post('/vehicle-requests/{vehicleRequest}/decline', [VehicleRequestController::class, 'declineInApp'])->name('vehicle-requests.decline.inapp')->middleware('permission:vehicles.dc-approve');
    Route::post('/vehicle-requests/{vehicleRequest}/sign-completion', [VehicleRequestController::class, 'signCompletion'])->name('vehicle-requests.sign-completion');
    Route::get('/vehicle-bookings', [VehicleRequestController::class, 'bookings'])->name('vehicle-requests.bookings');

    // Driver assignment API
    Route::get('/api/drivers', [\App\Http\Controllers\DriverController::class, 'index'])->name('api.drivers.index')->middleware('permission:vehicles.manage');
    Route::post('/vehicle-requests/{vehicleRequest}/assign-driver', [\App\Http\Controllers\DriverController::class, 'assign'])->name('vehicle-requests.assign-driver')->middleware('permission:vehicles.manage');

    // Signed email-link approvals (Division Chief / OCD)
    Route::get('/vehicle-requests/{vehicleRequest}/approve/{chief}', [VehicleRequestController::class, 'approveByDivisionChief'])
        ->name('vehicle-requests.approve')->middleware(['signed']);
    Route::get('/vehicle-requests/{vehicleRequest}/decline/{chief}', [VehicleRequestController::class, 'showDeclineForm'])
        ->name('vehicle-requests.decline')->middleware(['signed']);
    Route::post('/vehicle-requests/{vehicleRequest}/decline/{chief}', [VehicleRequestController::class, 'submitDecline'])
        ->name('vehicle-requests.decline.submit')->middleware(['signed']);
    Route::get('/vehicle-requests/{vehicleRequest}/ocd/approve/{ocd}', [VehicleRequestController::class, 'approveByOCD'])
        ->name('vehicle-requests.ocd.approve')->middleware(['signed']);
    Route::get('/vehicle-requests/{vehicleRequest}/ocd/decline/{ocd}', [VehicleRequestController::class, 'showOcdDeclineForm'])
        ->name('vehicle-requests.ocd.decline')->middleware(['signed']);
    Route::post('/vehicle-requests/{vehicleRequest}/ocd/decline/{ocd}', [VehicleRequestController::class, 'submitOcdDecline'])
        ->name('vehicle-requests.ocd.decline.submit')->middleware(['signed']);

    Route::get('/vehicle-requests/{vehicleRequest}/print', [VehicleRequestController::class, 'printTicket'])
        ->name('vehicle-requests.print')->middleware('permission:vehicles.manage');
    Route::middleware('permission:vehicles.manage')->group(function () {
        Route::put('/vehicle-requests/{vehicleRequest}', [VehicleRequestController::class, 'update'])->name('vehicle-requests.update');
        Route::delete('/vehicle-requests/{vehicleRequest}', [VehicleRequestController::class, 'destroy'])->name('vehicle-requests.destroy');
    });

    // ── Facility Requests ─────────────────────────────────────────────────────
    Route::get('/facility-bookings', [\App\Http\Controllers\FacilityRequestController::class, 'bookings'])->name('facility-requests.bookings');
    Route::get('/facility-requests/by-date', [\App\Http\Controllers\FacilityRequestController::class, 'byDate'])->name('facility-requests.byDate');
    Route::get('/facility-requests', [\App\Http\Controllers\FacilityRequestController::class, 'index'])->name('facility-requests.index');
    Route::post('/facility-requests', [\App\Http\Controllers\FacilityRequestController::class, 'store'])->name('facility-requests.store');
    Route::post('/facility-requests/{facilityRequest}/approve', [\App\Http\Controllers\FacilityRequestController::class, 'approveInApp'])->name('facility-requests.approve.inapp')->middleware('permission:facilities.dc-approve');
    Route::post('/facility-requests/{facilityRequest}/decline', [\App\Http\Controllers\FacilityRequestController::class, 'declineInApp'])->name('facility-requests.decline.inapp')->middleware('permission:facilities.dc-approve');
    Route::post('/facility-requests/{facilityRequest}/sign-completion', [\App\Http\Controllers\FacilityRequestController::class, 'signCompletion'])->name('facility-requests.sign-completion');

    Route::get('/facility-requests/{facilityRequest}/approve/{chief}', [\App\Http\Controllers\FacilityRequestController::class, 'approveByDivisionChief'])
        ->name('facility-requests.approve')->middleware(['signed']);
    Route::get('/facility-requests/{facilityRequest}/decline/{chief}', [\App\Http\Controllers\FacilityRequestController::class, 'showDeclineForm'])
        ->name('facility-requests.decline')->middleware(['signed']);
    Route::post('/facility-requests/{facilityRequest}/decline/{chief}', [\App\Http\Controllers\FacilityRequestController::class, 'submitDecline'])
        ->name('facility-requests.decline.submit')->middleware(['signed']);
    Route::get('/facility-requests/{facilityRequest}/gsu/approve/{gsu}', [\App\Http\Controllers\FacilityRequestController::class, 'approveByGSU'])
        ->name('facility-requests.gsu.approve')->middleware(['signed']);
    Route::get('/facility-requests/{facilityRequest}/gsu/decline/{gsu}', [\App\Http\Controllers\FacilityRequestController::class, 'showGsuDeclineForm'])
        ->name('facility-requests.gsu.decline')->middleware(['signed']);
    Route::post('/facility-requests/{facilityRequest}/gsu/decline/{gsu}', [\App\Http\Controllers\FacilityRequestController::class, 'submitGsuDecline'])
        ->name('facility-requests.gsu.decline.submit')->middleware(['signed']);
    Route::get('/facility-requests/{facilityRequest}/fad/approve/{chief}', [\App\Http\Controllers\FacilityRequestController::class, 'approveByFAD'])
        ->name('facility-requests.fad.approve')->middleware(['signed']);
    Route::get('/facility-requests/{facilityRequest}/fad/decline/{chief}', [\App\Http\Controllers\FacilityRequestController::class, 'showFadDeclineForm'])
        ->name('facility-requests.fad.decline')->middleware(['signed']);
    Route::post('/facility-requests/{facilityRequest}/fad/decline/{chief}', [\App\Http\Controllers\FacilityRequestController::class, 'submitFadDecline'])
        ->name('facility-requests.fad.decline.submit')->middleware(['signed']);

    Route::get('/facility-requests/{facilityRequest}/print', [\App\Http\Controllers\FacilityRequestController::class, 'printTicket'])
        ->name('facility-requests.print')->middleware('permission:facilities.manage');
    Route::middleware('permission:facilities.manage')->group(function () {
        Route::put('/facility-requests/{facilityRequest}', [\App\Http\Controllers\FacilityRequestController::class, 'update'])->name('facility-requests.update');
        Route::delete('/facility-requests/{facilityRequest}', [\App\Http\Controllers\FacilityRequestController::class, 'destroy'])->name('facility-requests.destroy');
    });

    // ── Work Requests ─────────────────────────────────────────────────────────
    Route::get('/work-requests', [WorkRequestController::class, 'index'])->name('work-requests.index')->middleware('permission:facilities.view');
    Route::post('/work-requests', [WorkRequestController::class, 'store'])->name('work-requests.store')->middleware('permission:facilities.create');
    Route::put('/work-requests/{workRequest}', [WorkRequestController::class, 'update'])->name('work-requests.update')->middleware('permission:facilities.create');
    Route::delete('/work-requests/{workRequest}', [WorkRequestController::class, 'destroy'])->name('work-requests.destroy')->middleware('permission:facilities.create');
    Route::post('/work-requests/{workRequest}/complete', [WorkRequestController::class, 'complete'])
        ->name('work-requests.complete')->middleware('permission:facilities.manage');
    Route::get('/work-requests/{workRequest}/print', [WorkRequestController::class, 'print'])
        ->name('work-requests.print')->middleware('permission:facilities.manage');

    Route::post('/work-requests/{workRequest}/approve', [WorkRequestController::class, 'approveInApp'])->name('work-requests.approve.inapp')->middleware('permission:facilities.dc-approve');
    Route::post('/work-requests/{workRequest}/decline', [WorkRequestController::class, 'declineInApp'])->name('work-requests.decline.inapp')->middleware('permission:facilities.dc-approve');

    Route::get('/work-requests/{workRequest}/approve/{chief}', [WorkRequestController::class, 'approveByDivisionChief'])
        ->name('work-requests.approve')->middleware(['signed']);
    Route::get('/work-requests/{workRequest}/decline/{chief}', [WorkRequestController::class, 'showDeclineForm'])
        ->name('work-requests.decline')->middleware(['signed']);
    Route::post('/work-requests/{workRequest}/decline/{chief}', [WorkRequestController::class, 'submitDecline'])
        ->name('work-requests.decline.submit')->middleware(['signed']);
    Route::get('/work-requests/{workRequest}/fad/approve/{chief}', [WorkRequestController::class, 'approveByFADChief'])
        ->name('work-requests.fad.approve')->middleware(['signed']);
    Route::get('/work-requests/{workRequest}/fad/decline/{chief}', [WorkRequestController::class, 'showFADDeclineForm'])
        ->name('work-requests.fad.decline')->middleware(['signed']);
    Route::post('/work-requests/{workRequest}/fad/decline/{chief}', [WorkRequestController::class, 'submitFADDecline'])
        ->name('work-requests.fad.decline.submit')->middleware(['signed']);
    // GSU Head signed links (hardened: now require a valid signature like every
    // other email-link approval — these were unsigned in the monolith)
    Route::get('/work-requests/{workRequest}/gsu/approve/{gsu}', [WorkRequestController::class, 'approveByGSUHead'])
        ->name('work-requests.gsu.approve')->middleware(['signed']);
    Route::get('/work-requests/{workRequest}/gsu/decline/{gsu}', [WorkRequestController::class, 'showGSUDeclineForm'])
        ->name('work-requests.gsu.decline')->middleware(['signed']);
    Route::post('/work-requests/{workRequest}/gsu/decline/{gsu}', [WorkRequestController::class, 'submitGSUDecline'])
        ->name('work-requests.gsu.decline.submit')->middleware(['signed']);

    // ── Service Requests ──────────────────────────────────────────────────────
    Route::get('/service-requests', [\App\Http\Controllers\ServiceRequestController::class, 'index'])->name('service-requests.index');
    Route::post('/service-requests', [\App\Http\Controllers\ServiceRequestController::class, 'store'])->name('service-requests.store');
    Route::post('/service-requests/{serviceRequest}/approve', [\App\Http\Controllers\ServiceRequestController::class, 'approveInApp'])->name('service-requests.approve.inapp')->middleware('permission:facilities.dc-approve');
    Route::post('/service-requests/{serviceRequest}/decline', [\App\Http\Controllers\ServiceRequestController::class, 'declineInApp'])->name('service-requests.decline.inapp')->middleware('permission:facilities.dc-approve');
    Route::put('/service-requests/{serviceRequest}', [\App\Http\Controllers\ServiceRequestController::class, 'update'])->name('service-requests.update');
    Route::delete('/service-requests/{serviceRequest}', [\App\Http\Controllers\ServiceRequestController::class, 'destroy'])->name('service-requests.destroy');

    Route::get('/service-requests/{serviceRequest}/approve/{chief}', [\App\Http\Controllers\ServiceRequestController::class, 'approveByDivisionChief'])
        ->name('service-requests.approve')->middleware(['signed']);
    Route::get('/service-requests/{serviceRequest}/decline/{chief}', [\App\Http\Controllers\ServiceRequestController::class, 'showDeclineForm'])
        ->name('service-requests.decline')->middleware(['signed']);
    Route::post('/service-requests/{serviceRequest}/decline/{chief}', [\App\Http\Controllers\ServiceRequestController::class, 'submitDecline'])
        ->name('service-requests.decline.submit')->middleware(['signed']);
    Route::get('/service-requests/{serviceRequest}/gsu/approve/{gsu}', [\App\Http\Controllers\ServiceRequestController::class, 'approveByGSU'])
        ->name('service-requests.gsu.approve')->middleware(['signed']);
    Route::get('/service-requests/{serviceRequest}/gsu/decline/{gsu}', [\App\Http\Controllers\ServiceRequestController::class, 'showDeclineForm'])
        ->name('service-requests.gsu.decline')->middleware(['signed']);
    Route::post('/service-requests/{serviceRequest}/gsu/decline/{gsu}', [\App\Http\Controllers\ServiceRequestController::class, 'submitDecline'])
        ->name('service-requests.gsu.decline.submit')->middleware(['signed']);
    Route::get('/service-requests/{serviceRequest}/fad/approve/{chief}', [\App\Http\Controllers\ServiceRequestController::class, 'approveByFAD'])
        ->name('service-requests.fad.approve')->middleware(['signed']);
    Route::get('/service-requests/{serviceRequest}/fad/decline/{chief}', [\App\Http\Controllers\ServiceRequestController::class, 'showFadDeclineForm'])
        ->name('service-requests.fad.decline')->middleware(['signed']);
    Route::post('/service-requests/{serviceRequest}/fad/decline/{chief}', [\App\Http\Controllers\ServiceRequestController::class, 'submitFadDecline'])
        ->name('service-requests.fad.decline.submit')->middleware(['signed']);

    Route::get('/service-requests/{serviceRequest}/print', [\App\Http\Controllers\ServiceRequestController::class, 'printTicket'])
        ->name('service-requests.print')->middleware('permission:facilities.manage');



    // ── Vehicles & Facilities masters ─────────────────────────────────────────
    Route::middleware('permission:vehicles.manage')->group(function () {
        Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
    });
    Route::middleware('permission:facilities.manage')->group(function () {
        Route::get('/facilities', [FacilityController::class, 'index'])->name('facilities.index');
        Route::post('/facilities', [FacilityController::class, 'store'])->name('facilities.store');
        Route::put('/facilities/{facility}', [FacilityController::class, 'update'])->name('facilities.update');
        Route::delete('/facilities/{facility}', [FacilityController::class, 'destroy'])->name('facilities.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | Users, Roles, Reports, Settings
    |--------------------------------------------------------------------------
    */
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store')->middleware('permission:users.manage');
        Route::put('users/{id}', [UserController::class, 'update'])->name('users.update')->middleware('permission:users.manage');
        Route::delete('users/{id}', [UserController::class, 'destroy'])->name('users.destroy')->middleware('permission:users.manage');
        Route::post('users/{user}/upload-signature', [UserController::class, 'uploadSignature'])->name('users.upload_signature')->middleware('permission:users.manage');
        Route::get('/users/inactive', [UserController::class, 'inactiveIndex'])->name('users.inactive')->middleware('permission:users.manage');
        Route::post('/users/{id}/activate', [UserController::class, 'activate'])->name('users.activate')->middleware('permission:users.manage');

        Route::get('/users-division', [RolesController::class, 'showDivisions'])->name('roles.divisions');
        Route::post('users-divisions', [RolesController::class, 'storeDivision'])->name('roles.divisions_store')->middleware('permission:roles.assign');
        Route::put('users-divisions/{id}', [RolesController::class, 'updateDivision'])->name('roles.division_update')->middleware('permission:roles.assign');
        Route::post('users-divisions/{division}/upload-signature', [RolesController::class, 'uploadSignature'])->name('roles.divisions.upload_signature')->middleware('permission:roles.assign');

        Route::get('/settings', fn () => Inertia::render('Settings/Index'))->name('settings');
    });

    // ── Reports ───────────────────────────────────────────────────────────────
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/reports', fn () => Inertia::render('Reports/Index'))->name('reports.index');
        Route::get('/reports/audit-logs', [\App\Http\Controllers\Reports\AuditLogController::class, 'index'])->name('reports.audit_logs')->middleware('permission:roles.assign');
    });

    // Lightweight users JSON endpoint for dropdowns
    Route::get('/api/users/select', [UserController::class, 'selectList'])->name('users.select');

    /*
    |--------------------------------------------------------------------------
    | Profile + Digital Signature
    |--------------------------------------------------------------------------
    */
    Route::get('/profile',   [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/profile/signature', [\App\Http\Controllers\UserSignatureController::class, 'show'])->name('profile.signature');
    Route::post('/profile/signature', [\App\Http\Controllers\UserSignatureController::class, 'saveSignature'])->name('profile.signature.save');
    Route::post('/profile/signature/pin', [\App\Http\Controllers\UserSignatureController::class, 'setPin'])->name('profile.signature.pin');
    Route::post('/profile/signature/verify-pin', [\App\Http\Controllers\UserSignatureController::class, 'verifyPin'])->name('profile.signature.verify-pin');
});

/*
|--------------------------------------------------------------------------
| RBAC Admin (pages + API)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'permission:roles.assign'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/roles',        fn () => Inertia::render('Admin/Roles/Index'))->name('roles');
        Route::get('/permissions',  fn () => Inertia::render('Admin/Roles/Permissions'))->name('permissions');
        Route::get('/assign-roles', fn () => Inertia::render('Admin/Users/AssignRoles'))->name('assign-roles');
    });

Route::middleware(['auth', 'permission:roles.assign'])
    ->prefix('admin/rbac')
    ->name('admin.rbac.')
    ->group(function () {
        // Roles
        Route::get('/roles',                    [\App\Http\Controllers\Admin\RoleController::class, 'index'])->name('roles.index');
        Route::post('/roles',                   [\App\Http\Controllers\Admin\RoleController::class, 'store'])->name('roles.store');
        Route::get('/roles/{role}',             [\App\Http\Controllers\Admin\RoleController::class, 'show'])->name('roles.show');
        Route::put('/roles/{role}',             [\App\Http\Controllers\Admin\RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}',          [\App\Http\Controllers\Admin\RoleController::class, 'destroy'])->name('roles.destroy');
        Route::put('/roles/{role}/permissions', [\App\Http\Controllers\Admin\RoleController::class, 'syncPermissions'])->name('roles.permissions.sync');
        Route::get('/permissions-all',          [\App\Http\Controllers\Admin\RoleController::class, 'allPermissions'])->name('permissions.all');

        // Permissions
        Route::get('/permissions',              [\App\Http\Controllers\Admin\PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions',             [\App\Http\Controllers\Admin\PermissionController::class, 'store'])->name('permissions.store');
        Route::put('/permissions/{permission}', [\App\Http\Controllers\Admin\PermissionController::class, 'update'])->name('permissions.update');
        Route::delete('/permissions/{permission}', [\App\Http\Controllers\Admin\PermissionController::class, 'destroy'])->name('permissions.destroy');

        // User → Role assignment
        Route::get('/users',                    [\App\Http\Controllers\Admin\UserRoleController::class, 'index'])->name('users.index');
        Route::get('/users/{user}',             [\App\Http\Controllers\Admin\UserRoleController::class, 'show'])->name('users.show');
        Route::put('/users/{user}/roles',       [\App\Http\Controllers\Admin\UserRoleController::class, 'sync'])->name('users.roles.sync');
        Route::get('/roles-list',               [\App\Http\Controllers\Admin\UserRoleController::class, 'rolesList'])->name('roles.list');
    });

/*
|--------------------------------------------------------------------------
| Organizational Structure (Data Management)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified'])->prefix('hr/org')->name('hr.org.')->group(function () {

    // Chart / tree views (read — org.view)
    Route::get('/', [\App\Http\Controllers\HR\OrgUnitController::class, 'index'])
        ->middleware('permission:org.view')->name('index');
    Route::get('/tree', [\App\Http\Controllers\HR\OrgUnitController::class, 'tree'])
        ->middleware('permission:org.view')->name('tree');
    Route::get('/units', [\App\Http\Controllers\HR\OrgUnitController::class, 'list'])
        ->middleware('permission:org.view')->name('units.list');
    Route::get('/units/{unit}', [\App\Http\Controllers\HR\OrgUnitController::class, 'show'])
        ->middleware('permission:org.view')->name('units.show');

    // Unit CRUD
    Route::post('/units', [\App\Http\Controllers\HR\OrgUnitController::class, 'store'])
        ->middleware('permission:org.units.create')->name('units.store');
    Route::put('/units/{unit}', [\App\Http\Controllers\HR\OrgUnitController::class, 'update'])
        ->middleware('permission:org.units.update')->name('units.update');
    Route::patch('/units/{unit}/move', [\App\Http\Controllers\HR\OrgUnitController::class, 'move'])
        ->middleware('permission:org.units.update')->name('units.move');
    Route::delete('/units/{unit}', [\App\Http\Controllers\HR\OrgUnitController::class, 'destroy'])
        ->middleware('permission:org.units.delete')->name('units.destroy');
    Route::post('/units/{unit}/restore', [\App\Http\Controllers\HR\OrgUnitController::class, 'restore'])
        ->middleware('permission:org.units.update')->name('units.restore');

    // Employee assignments
    Route::get('/units/{unit}/assignments', [\App\Http\Controllers\HR\EmployeeUnitAssignmentController::class, 'index'])
        ->middleware('permission:org.view')->name('assignments.index');
    Route::get('/employees/{user}/assignments', [\App\Http\Controllers\HR\EmployeeUnitAssignmentController::class, 'forEmployee'])
        ->middleware('permission:org.view')->name('assignments.for-employee');
    Route::post('/assignments', [\App\Http\Controllers\HR\EmployeeUnitAssignmentController::class, 'store'])
        ->middleware('permission:org.assign.manage')->name('assignments.store');
    Route::put('/assignments/{assignment}', [\App\Http\Controllers\HR\EmployeeUnitAssignmentController::class, 'update'])
        ->middleware('permission:org.assign.manage')->name('assignments.update');
    Route::patch('/assignments/{assignment}/end', [\App\Http\Controllers\HR\EmployeeUnitAssignmentController::class, 'end'])
        ->middleware('permission:org.assign.manage')->name('assignments.end');
    Route::delete('/assignments/{assignment}', [\App\Http\Controllers\HR\EmployeeUnitAssignmentController::class, 'destroy'])
        ->middleware('permission:org.assign.manage')->name('assignments.destroy');

    // Unit heads
    Route::get('/heads', [\App\Http\Controllers\HR\UnitHeadController::class, 'allCurrent'])
        ->middleware('permission:org.view')->name('heads.all');
    Route::get('/units/{unit}/heads', [\App\Http\Controllers\HR\UnitHeadController::class, 'index'])
        ->middleware('permission:org.view')->name('heads.index');
    Route::post('/heads', [\App\Http\Controllers\HR\UnitHeadController::class, 'store'])
        ->middleware('permission:org.heads.manage')->name('heads.store');
    Route::put('/heads/{head}', [\App\Http\Controllers\HR\UnitHeadController::class, 'update'])
        ->middleware('permission:org.heads.manage')->name('heads.update');
    Route::patch('/heads/{head}/end', [\App\Http\Controllers\HR\UnitHeadController::class, 'end'])
        ->middleware('permission:org.heads.manage')->name('heads.end');
    Route::delete('/heads/{head}', [\App\Http\Controllers\HR\UnitHeadController::class, 'destroy'])
        ->middleware('permission:org.heads.manage')->name('heads.destroy');

    // Sync from legacy divisions/offices
    Route::post('/sync-legacy', [\App\Http\Controllers\HR\OrgUnitController::class, 'syncFromLegacy'])
        ->middleware('permission:org.units.create')->name('sync-legacy');

    // Reports
    Route::get('/reports', [\App\Http\Controllers\HR\OrgUnitController::class, 'reports'])
        ->middleware('permission:org.reports')->name('reports');

    // Export & Print
    Route::get('/print', [\App\Http\Controllers\HR\OrgExportController::class, 'print'])
        ->middleware('permission:org.export')->name('print');
    Route::get('/export/pdf', [\App\Http\Controllers\HR\OrgExportController::class, 'pdf'])
        ->middleware('permission:org.export')->name('export.pdf');
    Route::get('/export/units-csv', [\App\Http\Controllers\HR\OrgExportController::class, 'unitsCsv'])
        ->middleware('permission:org.export')->name('export.units-csv');
    Route::get('/export/assignments-csv', [\App\Http\Controllers\HR\OrgExportController::class, 'assignmentsCsv'])
        ->middleware('permission:org.export')->name('export.assignments-csv');

    // Structural versioning
    Route::get('/versions', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'index'])
        ->middleware('permission:org.versions.view')->name('versions.index');
    Route::get('/versions/current', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'current'])
        ->middleware('permission:org.versions.view')->name('versions.current');
    Route::get('/versions/{version}', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'show'])
        ->middleware('permission:org.versions.view')->name('versions.show');
    Route::post('/versions', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'store'])
        ->middleware('permission:org.versions.manage')->name('versions.store');
    Route::post('/versions/{version}/approve', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'approve'])
        ->middleware('permission:org.versions.manage')->name('versions.approve');
    Route::post('/versions/{version}/activate', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'activate'])
        ->middleware('permission:org.versions.manage')->name('versions.activate');
    Route::delete('/versions/{version}', [\App\Http\Controllers\HR\OrganizationalVersionController::class, 'destroy'])
        ->middleware('permission:org.versions.manage')->name('versions.destroy');
});

/*
|--------------------------------------------------------------------------
| Chat + Auth scaffolding
|--------------------------------------------------------------------------
*/
require __DIR__.'/chat.php';
require __DIR__.'/auth.php';
