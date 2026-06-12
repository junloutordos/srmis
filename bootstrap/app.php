<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        apiPrefix: 'api',
        then: function () {
            // Tenant routes — single domain. The tenant is resolved per request
            // by App\Tenancy\ResolveTenant (in the web group): session binding,
            // signed ?tenant= parameter, or login-email inference.
            // EnsureModuleEnabled (no parameter) infers the module from the
            // request path and enforces the tenant's module activation map.
            Route::middleware([
                'web',
                \App\Http\Middleware\EnsureModuleEnabled::class,
            ])->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies so HTTPS is detected correctly behind Cloudflare → ALB
        $middleware->trustProxies(at: '*');

        // The campus tenant MUST be resolved after the session is available but
        // BEFORE authentication — otherwise the auth user lookup hits the
        // central connection. Laravel's middleware priority sorter would put
        // `auth` ahead of any unlisted middleware, so list ours explicitly.
        $middleware->priority([
            \Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests::class,
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Tenancy\ResolveTenant::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Auth\Middleware\Authenticate::class,
            \Illuminate\Routing\Middleware\ThrottleRequests::class,
            \Illuminate\Routing\Middleware\ThrottleRequestsWithRedis::class,
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            // Validate URL signatures BEFORE route-model binding touches the
            // database — a tampered/forged link must die with 403, not reach
            // a query without tenant context.
            \Illuminate\Routing\Middleware\ValidateSignature::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \Illuminate\Auth\Middleware\Authorize::class,
        ]);

        $middleware->web(append: [
            // Must run before Inertia shares auth data — resolves the campus
            // tenant from the session/signed-link/login email each request.
            \App\Tenancy\ResolveTenant::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Register custom middleware
        $middleware->alias([
            'role'                => \App\Http\Middleware\RoleMiddleware::class,
            'permission'          => \App\Http\Middleware\CheckPermission::class,
            'allowed.domain'      => \App\Http\Middleware\EnsureAllowedEmailDomain::class,
            'setup.not-installed' => \App\Http\Middleware\EnsureNotInstalled::class,
            'module'              => \App\Http\Middleware\EnsureModuleEnabled::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
