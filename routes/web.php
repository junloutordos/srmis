<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SRMIS Central Routes (single domain)
|--------------------------------------------------------------------------
|
| All 17 campuses and the OED share ONE domain. Campus (tenant) routes live
| in routes/tenant.php; the tenant is resolved per request from the session
| binding set at login (see App\Tenancy\ResolveTenant).
|
| This file holds the instance-level plane, namespaced under paths tenant
| routes never use:
|   /health    — ECS/ALB health check
|   /setup     — first-run setup wizard (404s once installed)
|   /system/*  — system superadmin: tenant provisioning + module toggles
|
*/

// ECS container health check — no auth, no session
Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');

// Root — wizard when not installed, otherwise the campus login page
Route::get('/', [\App\Http\Controllers\Central\LandingController::class, 'index'])->name('central.landing');

/*
|--------------------------------------------------------------------------
| First-run Setup Wizard
|--------------------------------------------------------------------------
| Accessible only while the instance is NOT yet installed (guarded by the
| `setup.not-installed` middleware). Once installation completes the whole
| group returns 404.
*/
Route::prefix('setup')->name('setup.')->middleware('setup.not-installed')->group(function () {
    Route::get('/',                [\App\Http\Controllers\Central\SetupWizardController::class, 'index'])->name('index');
    Route::get('/state',           [\App\Http\Controllers\Central\SetupWizardController::class, 'state'])->name('state');
    Route::post('/database',       [\App\Http\Controllers\Central\SetupWizardController::class, 'checkDatabase'])->name('database');
    Route::post('/domain',         [\App\Http\Controllers\Central\SetupWizardController::class, 'saveDomain'])->name('domain');
    Route::post('/storage',        [\App\Http\Controllers\Central\SetupWizardController::class, 'saveStorage'])->name('storage');
    Route::post('/websockets',     [\App\Http\Controllers\Central\SetupWizardController::class, 'saveWebsockets'])->name('websockets');
    Route::post('/superadmin',     [\App\Http\Controllers\Central\SetupWizardController::class, 'registerSuperadmin'])->name('superadmin');
    Route::post('/finish',         [\App\Http\Controllers\Central\SetupWizardController::class, 'finish'])->name('finish');
});

/*
|--------------------------------------------------------------------------
| System Administration (system superadmin) — /system/*
|--------------------------------------------------------------------------
| Tenant provisioning and per-tenant module activation. Auth is against the
| central_users table (system superadmins only) — completely separate from
| campus accounts. Lives under /system so it never collides with the tenant
| RBAC pages at /admin/*.
*/
Route::prefix('system')->group(function () {
    Route::middleware('guest:central')->group(function () {
        Route::get('/login',  [\App\Http\Controllers\Central\AuthController::class, 'create'])->name('central.login');
        Route::post('/login', [\App\Http\Controllers\Central\AuthController::class, 'store'])
            ->middleware('throttle:5,1')->name('central.login.store');
    });

    Route::middleware('auth:central')->group(function () {
        Route::post('/logout', [\App\Http\Controllers\Central\AuthController::class, 'destroy'])->name('central.logout');

        Route::get('/', [\App\Http\Controllers\Central\TenantController::class, 'index'])->name('central.admin');

        // Tenant (campus) provisioning
        Route::get('/tenants',                    [\App\Http\Controllers\Central\TenantController::class, 'list'])->name('central.tenants.index');
        Route::post('/tenants',                   [\App\Http\Controllers\Central\TenantController::class, 'store'])->name('central.tenants.store');
        Route::post('/tenants/provision-presets', [\App\Http\Controllers\Central\TenantController::class, 'provisionPresets'])->name('central.tenants.presets');
        Route::put('/tenants/{tenant}',           [\App\Http\Controllers\Central\TenantController::class, 'update'])->name('central.tenants.update');
        Route::delete('/tenants/{tenant}',        [\App\Http\Controllers\Central\TenantController::class, 'destroy'])->name('central.tenants.destroy');

        // Per-tenant module activation toggles
        Route::put('/tenants/{tenant}/modules',   [\App\Http\Controllers\Central\TenantModuleController::class, 'update'])->name('central.tenants.modules');

        // Per-tenant seeding / migration triggers
        Route::post('/tenants/{tenant}/migrate',  [\App\Http\Controllers\Central\TenantController::class, 'migrate'])->name('central.tenants.migrate');
        Route::post('/tenants/{tenant}/seed-admin', [\App\Http\Controllers\Central\TenantController::class, 'seedAdmin'])->name('central.tenants.seed-admin');
    });
});
