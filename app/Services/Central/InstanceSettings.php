<?php

namespace App\Services\Central;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Key-value settings for this SRMIS instance, written by the setup wizard
 * (instance domain, S3 credentials, WebSocket credentials, installed flag).
 *
 * Secrets are encrypted at rest with the app key. Reads tolerate a missing
 * table so the app can boot before the central migrations have run.
 */
class InstanceSettings
{
    public const INSTALLED_KEY = 'installed';

    /** Keys that must always be stored encrypted. */
    public const SECRET_KEYS = [
        's3.secret',
        'websockets.app_secret',
    ];

    public function isInstalled(): bool
    {
        return (bool) $this->get(self::INSTALLED_KEY, false);
    }

    public function markInstalled(): void
    {
        $this->set(self::INSTALLED_KEY, '1');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (! $this->tableExists()) {
            return $default;
        }

        $row = DB::connection('central')->table('instance_settings')->where('key', $key)->first();

        if ($row === null || $row->value === null) {
            return $default;
        }

        return $row->encrypted ? Crypt::decryptString($row->value) : $row->value;
    }

    public function set(string $key, ?string $value, ?bool $encrypted = null): void
    {
        $encrypted ??= in_array($key, self::SECRET_KEYS, true);

        DB::connection('central')->table('instance_settings')->updateOrInsert(
            ['key' => $key],
            [
                'value'      => $encrypted && $value !== null ? Crypt::encryptString($value) : $value,
                'encrypted'  => $encrypted,
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    /** @param array<string, ?string> $values */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value);
        }
    }

    protected function tableExists(): bool
    {
        try {
            return Schema::connection('central')->hasTable('instance_settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
