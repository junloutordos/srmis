<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Swap the URL generator for one that embeds the current tenant in
        // signed URLs (single-domain tenancy — see TenantAwareUrlGenerator).
        $this->app->extend('url', function ($url, $app) {
            $generator = new \App\Tenancy\TenantAwareUrlGenerator(
                $app['router']->getRoutes(),
                $app->rebinding('request', fn ($app, $request) => $app['url']->setRequest($request)),
                $app['config']['app.asset_url'],
            );

            $generator->setSessionResolver(fn () => $app['session'] ?? null);
            $generator->setKeyResolver(function () use ($app) {
                $config = $app->make('config');

                return [$config->get('app.key'), ...($config->get('app.previous_keys') ?? [])];
            });

            return $generator;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ── Password Policy ───────────────────────────────────────────────────
        // Enforce: min 10 chars, at least 1 letter, 1 number, 1 symbol
        Password::defaults(fn () =>
            Password::min(10)
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
        );

        // ── Dynamic permission gates ───────────────────────────────────────────
        // SuperAdmins bypass all checks. For everyone else, any ability that
        // matches a permission name in the DB is resolved via the user's
        // permission cache — no per-permission gate registration needed.
        // Central (system superadmin) users never reach tenant gates; the
        // instanceof guard keeps them from matching tenant permissions.
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) return null;
            if ($user->isSuperAdmin()) return true;
            if ($user->hasPermission($ability)) return true;
            return null; // fall through to policies
        });

        Vite::prefetch(concurrency: 3);

        // Ensure preload tags for CSS use the correct `as` attribute so browsers
        // don't warn about preloaded stylesheets that are not used immediately.
        Vite::usePreloadTagAttributes(function ($src, $url, $chunk, $manifest) {
            if (str_ends_with($url, '.css')) {
                return ['as' => 'style'];
            }

            return [];
        });

        // Listen to Eloquent model events globally and record audit logs.
        Event::listen('eloquent.created: *', function ($eventName, $payload) {
            $model = $payload[0] ?? null;
            if ($model && !($model instanceof \App\Models\AuditLog)) {
                AuditLogger::logModelEvent($model, 'created');
            }
        });

        Event::listen('eloquent.updated: *', function ($eventName, $payload) {
            $model = $payload[0] ?? null;
            if ($model && !($model instanceof \App\Models\AuditLog)) {
                AuditLogger::logModelEvent($model, 'updated');
            }
        });

        Event::listen('eloquent.deleted: *', function ($eventName, $payload) {
            $model = $payload[0] ?? null;
            if ($model && !($model instanceof \App\Models\AuditLog)) {
                AuditLogger::logModelEvent($model, 'deleted');
            }
        });

        // Authentication events
        Event::listen(Login::class, function (Login $event) {
            AuditLogger::log([
                'action' => 'login',
                'auditable_type' => get_class($event->user),
                'auditable_id' => $event->user->getKey(),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            AuditLogger::log([
                'action' => 'logout',
                'auditable_type' => $event->user ? get_class($event->user) : null,
                'auditable_id' => $event->user ? $event->user->getKey() : null,
            ]);
        });
    }
}
