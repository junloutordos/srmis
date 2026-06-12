<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\CentralUser;
use App\Models\Central\Tenant;
use App\Services\Central\CampusPresets;
use App\Services\Central\InstanceSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

/**
 * First-run self-service setup wizard for a fresh SRMIS instance.
 *
 * Every route is wrapped in the `setup.not-installed` middleware — once
 * finish() marks the instance installed, the wizard 404s permanently.
 *
 * Steps:
 *   1. database    — verify connectivity, run central migrations
 *   2. domain      — instance (central) domain for subdomain routing
 *   3. storage     — S3 credentials (encrypted at rest)
 *   4. websockets  — Soketi/Pusher credentials (secret encrypted at rest)
 *   5. superadmin  — first system superadmin account
 *   6. finish      — provision selected campus tenants + module toggles,
 *                    mark installed, log the superadmin in
 */
class SetupWizardController extends Controller
{
    public function __construct(protected InstanceSettings $settings)
    {
    }

    public function index()
    {
        return Inertia::render('Central/Setup', [
            'campuses'   => CampusPresets::CAMPUSES,
            'modules'    => Tenant::MODULES,
            'state'      => $this->wizardState(),
            'phpVersion' => PHP_VERSION,
        ]);
    }

    public function state()
    {
        return response()->json($this->wizardState());
    }

    /** Step 1 — verify DB connectivity and run central migrations. */
    public function checkDatabase()
    {
        try {
            DB::connection('central')->select('SELECT 1');
        } catch (\Throwable $e) {
            return response()->json([
                'ok'    => false,
                'error' => 'Cannot connect to the database. Check the DB_* variables in the container environment / SSM parameters. (' . $e->getMessage() . ')',
            ], 422);
        }

        Artisan::call('migrate', ['--force' => true]);

        return response()->json([
            'ok'       => true,
            'database' => DB::connection('central')->getDatabaseName(),
            'migrated' => Schema::connection('central')->hasTable('instance_settings'),
        ]);
    }

    /** Step 2 — instance domain. */
    public function saveDomain(Request $request)
    {
        $data = $request->validate([
            'domain' => ['required', 'string', 'max:190', 'regex:/^[a-z0-9.-]+$/'],
        ]);

        $this->settings->set('instance.domain', $data['domain']);

        return response()->json(['ok' => true]);
    }

    /** Step 3 — S3 storage credentials. */
    public function saveStorage(Request $request)
    {
        $data = $request->validate([
            'key'    => ['required', 'string', 'max:190'],
            'secret' => ['required', 'string', 'max:190'],
            'region' => ['required', 'string', 'max:60'],
            'bucket' => ['required', 'string', 'max:190'],
        ]);

        $this->settings->setMany([
            's3.key'    => $data['key'],
            's3.secret' => $data['secret'],
            's3.region' => $data['region'],
            's3.bucket' => $data['bucket'],
        ]);

        return response()->json(['ok' => true]);
    }

    /** Step 4 — WebSocket (Soketi / Pusher-compatible) credentials. */
    public function saveWebsockets(Request $request)
    {
        $data = $request->validate([
            'app_id'     => ['required', 'string', 'max:190'],
            'app_key'    => ['required', 'string', 'max:190'],
            'app_secret' => ['required', 'string', 'max:190'],
            'host'       => ['required', 'string', 'max:190'],
            'port'       => ['required', 'integer', 'between:1,65535'],
            'scheme'     => ['required', 'in:http,https'],
        ]);

        $this->settings->setMany([
            'websockets.app_id'     => $data['app_id'],
            'websockets.app_key'    => $data['app_key'],
            'websockets.app_secret' => $data['app_secret'],
            'websockets.host'       => $data['host'],
            'websockets.port'       => (string) $data['port'],
            'websockets.scheme'     => $data['scheme'],
        ]);

        return response()->json(['ok' => true]);
    }

    /** Step 5 — register the first system superadmin. */
    public function registerSuperadmin(Request $request)
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:190'],
            'email'    => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        // Wizard runs once; only ever one superadmin from this path.
        if (CentralUser::query()->exists()) {
            return response()->json([
                'ok'    => false,
                'error' => 'A superadmin account already exists.',
            ], 422);
        }

        $user = CentralUser::create($data);

        auth('central')->login($user);
        $request->session()->regenerate();

        return response()->json(['ok' => true]);
    }

    /**
     * Step 6 — provision the selected campus tenants (with per-tenant module
     * toggles) and mark the instance installed.
     */
    public function finish(Request $request)
    {
        $data = $request->validate([
            'tenants'                 => ['required', 'array', 'min:1'],
            'tenants.*.slug'          => ['required', 'string', 'max:30', 'regex:/^[a-z0-9-]+$/'],
            'tenants.*.name'          => ['required', 'string', 'max:190'],
            'tenants.*.campus_code'   => ['required', 'string', 'max:20'],
            'tenants.*.modules'       => ['sometimes', 'array'],
        ]);

        if (! auth('central')->check()) {
            return response()->json(['ok' => false, 'error' => 'Register the superadmin account first.'], 422);
        }

        $provisioned = [];
        $failed      = [];

        foreach ($data['tenants'] as $row) {
            if (Tenant::find($row['slug'])) {
                continue; // already provisioned (wizard retry)
            }

            try {
                $tenant = Tenant::create([
                    'id'          => $row['slug'],
                    'name'        => $row['name'],
                    'campus_code' => $row['campus_code'],
                    'modules'     => $this->moduleMap($row['modules'] ?? []),
                ]);

                $tenant->domains()->create(['domain' => $row['slug']]);

                $provisioned[] = $row['slug'];
            } catch (\Throwable $e) {
                report($e);
                $failed[] = ['slug' => $row['slug'], 'error' => $e->getMessage()];
            }
        }

        if ($failed !== []) {
            return response()->json([
                'ok'          => false,
                'provisioned' => $provisioned,
                'failed'      => $failed,
            ], 422);
        }

        $this->settings->markInstalled();

        return response()->json([
            'ok'          => true,
            'provisioned' => $provisioned,
            'redirect'    => route('central.admin'),
        ]);
    }

    /** Normalize a checkbox map to bool flags for every known module. */
    protected function moduleMap(array $selected): array
    {
        $map = [];

        foreach (array_keys(Tenant::MODULES) as $module) {
            $map[$module] = (bool) ($selected[$module] ?? true);
        }

        return $map;
    }

    protected function wizardState(): array
    {
        $dbOk = true;

        try {
            DB::connection('central')->select('SELECT 1');
        } catch (\Throwable) {
            $dbOk = false;
        }

        return [
            'database_ok'    => $dbOk,
            'migrated'       => $dbOk && Schema::connection('central')->hasTable('instance_settings'),
            'domain'         => $this->settings->get('instance.domain'),
            'storage_saved'  => (bool) $this->settings->get('s3.bucket'),
            'sockets_saved'  => (bool) $this->settings->get('websockets.app_key'),
            'superadmin_set' => $dbOk
                && Schema::connection('central')->hasTable('central_users')
                && CentralUser::query()->exists(),
            'tenant_count'   => $dbOk && Schema::connection('central')->hasTable('tenants')
                ? Tenant::count()
                : 0,
        ];
    }
}
