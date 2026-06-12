<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant;
use App\Services\Central\CampusPresets;
use App\Services\Central\InstanceSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Central admin — campus tenant provisioning & lifecycle.
 * All routes require the `central` (system superadmin) guard.
 */
class TenantController extends Controller
{
    public function index(InstanceSettings $settings)
    {
        return Inertia::render('Central/Admin', [
            'tenants'  => $this->tenantList(),
            'campuses' => CampusPresets::CAMPUSES,
            'modules'  => Tenant::MODULES,
            'domain'   => $settings->get('instance.domain', config('app.url')),
        ]);
    }

    public function list()
    {
        return response()->json(['tenants' => $this->tenantList()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'slug'        => ['required', 'string', 'max:30', 'regex:/^[a-z0-9-]+$/', 'unique:tenants,id'],
            'name'        => ['required', 'string', 'max:190'],
            'campus_code' => ['required', 'string', 'max:20'],
            'modules'     => ['sometimes', 'array'],
        ]);

        $tenant = Tenant::create([
            'id'          => $data['slug'],
            'name'        => $data['name'],
            'campus_code' => $data['campus_code'],
            'modules'     => $this->moduleMap($data['modules'] ?? []),
        ]);

        $tenant->domains()->create(['domain' => $data['slug']]);

        return back()->with('success', "Campus '{$data['name']}' provisioned — schema " . config('tenancy.database.prefix') . $data['slug'] . ' created and migrated.');
    }

    /** Provision every missing preset campus in one click. */
    public function provisionPresets()
    {
        $created = [];

        foreach (CampusPresets::CAMPUSES as $campus) {
            if (Tenant::find($campus['slug'])) {
                continue;
            }

            $tenant = Tenant::create([
                'id'          => $campus['slug'],
                'name'        => $campus['name'],
                'campus_code' => $campus['code'],
                'modules'     => $this->moduleMap([]),
            ]);

            $tenant->domains()->create(['domain' => $campus['slug']]);

            $created[] = $campus['slug'];
        }

        return back()->with('success', $created === []
            ? 'All preset campuses are already provisioned.'
            : 'Provisioned: ' . implode(', ', $created));
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:190'],
            'campus_code' => ['required', 'string', 'max:20'],
        ]);

        $tenant->update($data);

        return back()->with('success', 'Campus updated.');
    }

    public function destroy(Tenant $tenant)
    {
        // Deleting a tenant drops its schema (TenantDeleted pipeline) — this
        // is irreversible and intentionally requires explicit confirmation.
        abort_unless(request()->boolean('confirmed'), 422, 'Deletion must be confirmed.');

        $tenant->delete();

        return back()->with('success', 'Campus tenant deleted (schema dropped).');
    }

    /** Run pending tenant migrations for one tenant. */
    public function migrate(Tenant $tenant)
    {
        \Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id]]);

        return back()->with('success', "Migrations run for {$tenant->id}.");
    }

    /**
     * (Re-)seed RBAC roles/permissions for one tenant, and optionally create
     * (or reset) that campus's initial Administrator account.
     */
    public function seedAdmin(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'name'     => ['sometimes', 'required', 'string', 'max:190'],
            'email'    => ['required_with:password', 'nullable', 'email', 'max:190'],
            'password' => ['required_with:email', 'nullable', 'string', 'min:12'],
        ]);

        \Artisan::call('tenants:seed', [
            '--tenants' => [$tenant->id],
            '--class'   => 'TenantDatabaseSeeder',
            '--force'   => true,
        ]);

        if (! empty($data['email'])) {
            $tenant->run(function () use ($data) {
                $adminRole = \App\Models\Role::firstOrCreate(['name' => 'Administrator']);

                $user = \App\Models\User::updateOrCreate(
                    ['email' => $data['email']],
                    [
                        'name'              => $data['name'] ?? 'Campus Administrator',
                        'password'          => bcrypt($data['password']),
                        'role_id'           => $adminRole->id,
                        'status'            => 'active',
                        'email_verified_at' => now(),
                    ],
                );

                $user->roles()->syncWithoutDetaching([$adminRole->id]);
            });
        }

        return back()->with('success', "RBAC seeded for {$tenant->id}." . (! empty($data['email']) ? ' Campus admin account ready.' : ''));
    }

    protected function tenantList(): array
    {
        return Tenant::with('domains')->orderBy('id')->get()->map(fn (Tenant $t) => [
            'id'          => $t->id,
            'name'        => $t->name,
            'campus_code' => $t->campus_code,
            'subdomain'   => $t->domains->first()?->domain,
            'schema'      => config('tenancy.database.prefix') . $t->id,
            'modules'     => $t->modules,
            'created_at'  => $t->created_at?->toDateTimeString(),
        ])->all();
    }

    protected function moduleMap(array $selected): array
    {
        $map = [];

        foreach (array_keys(Tenant::MODULES) as $module) {
            $map[$module] = (bool) ($selected[$module] ?? true);
        }

        return $map;
    }
}
