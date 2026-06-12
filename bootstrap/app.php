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
            // Tenant routes — resolved by subdomain, isolated per campus schema.
            // EnsureModuleEnabled (no parameter) infers the module from the
            // request path and enforces the tenant's module activation map.
            Route::middleware([
                'web',
                \Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain::class,
                \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
                \App\Http\Middleware\EnsureModuleEnabled::class,
            ])->group(base_path('routes/tenant.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust all proxies so HTTPS is detected correctly behind Cloudflare → ALB
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
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
