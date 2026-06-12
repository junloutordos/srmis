<?php

namespace App\Providers;

use App\Services\Central\InstanceSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

/**
 * Applies settings saved by the setup wizard (S3 + WebSocket credentials)
 * on top of the env-based config, so a freshly installed instance works
 * without editing .env / SSM parameters by hand.
 *
 * Values present in the environment still win when the wizard never saved
 * that key. Failures are swallowed — before the central migrations run
 * there is no settings table, and the app must still boot.
 */
class InstanceConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            $settings = $this->app->make(InstanceSettings::class);

            $values = Cache::remember('instance.config-overrides', 60, function () use ($settings) {
                if (! $settings->isInstalled()) {
                    return [];
                }

                return [
                    's3.key'                => $settings->get('s3.key'),
                    's3.secret'             => $settings->get('s3.secret'),
                    's3.region'             => $settings->get('s3.region'),
                    's3.bucket'             => $settings->get('s3.bucket'),
                    'websockets.app_id'     => $settings->get('websockets.app_id'),
                    'websockets.app_key'    => $settings->get('websockets.app_key'),
                    'websockets.app_secret' => $settings->get('websockets.app_secret'),
                    'websockets.host'       => $settings->get('websockets.host'),
                    'websockets.port'       => $settings->get('websockets.port'),
                    'websockets.scheme'     => $settings->get('websockets.scheme'),
                ];
            });

            if (! empty($values['s3.bucket'])) {
                config([
                    'filesystems.disks.s3.key'    => $values['s3.key'],
                    'filesystems.disks.s3.secret' => $values['s3.secret'],
                    'filesystems.disks.s3.region' => $values['s3.region'],
                    'filesystems.disks.s3.bucket' => $values['s3.bucket'],
                ]);
            }

            if (! empty($values['websockets.app_key'])) {
                config([
                    'broadcasting.connections.pusher.app_id' => $values['websockets.app_id'],
                    'broadcasting.connections.pusher.key'    => $values['websockets.app_key'],
                    'broadcasting.connections.pusher.secret' => $values['websockets.app_secret'],
                    'broadcasting.connections.pusher.options.host'   => $values['websockets.host'],
                    'broadcasting.connections.pusher.options.port'   => $values['websockets.port'],
                    'broadcasting.connections.pusher.options.scheme' => $values['websockets.scheme'],
                    'broadcasting.connections.pusher.options.useTLS' => $values['websockets.scheme'] === 'https',
                ]);
            }
        } catch (\Throwable) {
            // Pre-install (no DB / no table) — boot with env config only.
        }
    }
}
