<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Root seeder for every tenant (campus) schema — runs automatically when a
 * tenant is provisioned (TenantCreated → SeedDatabase pipeline) and can be
 * re-run idempotently via `php artisan tenants:seed`.
 */
class TenantDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(TenantRolesSeeder::class);
        $this->call(TenantRoleLabelsSeeder::class);    // per-tenant role display overrides (e.g. OED's OCD -> KID Chief)
        $this->call(TenantPermissionsSeeder::class);   // also calls OrgStructurePermissionsSeeder
        $this->call(TenantRolePermissionSeeder::class);
        $this->call(ITJobCategorySeeder::class);
    }
}
