<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SRMIS Central Routes
|--------------------------------------------------------------------------
|
| Routes here run on the central (non-tenant) domain only — e.g.
| srmis.pshs.edu.ph. Campus subdomains (oed.*, crc.*, src.*) are handled
| by routes/tenant.php inside an initialized tenant context.
|
| The central domain hosts:
|   - the ECS/ALB health check
|   - the first-run setup wizard (system superadmin)
|   - tenant (campus) provisioning + per-tenant module activation
|
*/

// ECS container health check — no auth, no session, any host
Route::get('/health', fn () => response()->json(['status' => 'ok']))->name('health');

/*
 * Everything below is explicitly bound to the central domain(s) so these
 * paths never shadow tenant routes on campus subdomains.
 */
foreach (config('tenancy.central_domains') as $centralDomainIndex => $centralDomain) {
    Route::domain($centralDomain)->name($centralDomainIndex ? "c{$centralDomainIndex}." : '')->group(function () {

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
| Central Administration (system superadmin)
|--------------------------------------------------------------------------
| Tenant provisioning and per-tenant module activation. Auth is against the
| central users table (system superadmins only).
*/
Route::middleware('guest:central')->group(function () {
    Route::get('/login',  [\App\Http\Controllers\Central\AuthController::class, 'create'])->name('central.login');
    Route::post('/login', [\App\Http\Controllers\Central\AuthController::class, 'store'])
        ->middleware('throttle:5,1')->name('central.login.store');
});

Route::middleware('auth:central')->group(function () {
    Route::post('/logout', [\App\Http\Controllers\Central\AuthController::class, 'destroy'])->name('central.logout');

    Route::get('/admin', [\App\Http\Controllers\Central\TenantController::class, 'index'])->name('central.admin');

    // Tenant (campus) provisioning
    Route::get('/admin/tenants',                    [\App\Http\Controllers\Central\TenantController::class, 'list'])->name('central.tenants.index');
    Route::post('/admin/tenants',                   [\App\Http\Controllers\Central\TenantController::class, 'store'])->name('central.tenants.store');
    Route::post('/admin/tenants/provision-presets', [\App\Http\Controllers\Central\TenantController::class, 'provisionPresets'])->name('central.tenants.presets');
    Route::put('/admin/tenants/{tenant}',           [\App\Http\Controllers\Central\TenantController::class, 'update'])->name('central.tenants.update');
    Route::delete('/admin/tenants/{tenant}',        [\App\Http\Controllers\Central\TenantController::class, 'destroy'])->name('central.tenants.destroy');

    // Per-tenant module activation toggles
    Route::put('/admin/tenants/{tenant}/modules',   [\App\Http\Controllers\Central\TenantModuleController::class, 'update'])->name('central.tenants.modules');

    // Per-tenant seeding / migration triggers
    Route::post('/admin/tenants/{tenant}/migrate',  [\App\Http\Controllers\Central\TenantController::class, 'migrate'])->name('central.tenants.migrate');
    Route::post('/admin/tenants/{tenant}/seed-admin', [\App\Http\Controllers\Central\TenantController::class, 'seedAdmin'])->name('central.tenants.seed-admin');
});

    }); // end Route::domain($centralDomain)
}
