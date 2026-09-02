<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * The full SRMIS permission catalog. Every permission string referenced by
 * route middleware, controllers, or the sidebar must exist here — the RBAC
 * audit treats this file as the source of truth.
 */
class TenantPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // ── Users & Roles (Data Management) ──────────────────────────────
            ['module' => 'Users',   'name' => 'users.view',   'description' => 'View user list'],
            ['module' => 'Users',   'name' => 'users.manage', 'description' => 'Create, edit, deactivate and reactivate users'],
            ['module' => 'Roles',   'name' => 'roles.assign', 'description' => 'Manage roles, permissions, divisions and reference data'],

            // ── MIS ───────────────────────────────────────────────────────────
            ['module' => 'MIS', 'name' => 'it.requests.view',    'description' => 'View own IT job requests'],
            ['module' => 'MIS', 'name' => 'it.requests.manage',  'description' => 'Manage all IT job requests, MIS dashboard, CSM feedback'],
            ['module' => 'MIS', 'name' => 'it.requests.dispatch','description' => 'Dispatch OCD-approved IT job requests to MIS personnel'],
            ['module' => 'MIS', 'name' => 'it.equipment.view',   'description' => 'View ICT equipment inventory and PMS'],
            ['module' => 'MIS', 'name' => 'it.equipment.manage', 'description' => 'Manage ICT equipment inventory and PMS'],

            // ── General Services — Vehicles ──────────────────────────────────
            ['module' => 'GeneralServices', 'name' => 'vehicles.view',        'description' => 'View and file vehicle requests'],
            ['module' => 'GeneralServices', 'name' => 'vehicles.manage',      'description' => 'Manage vehicles, assign drivers, print trip tickets'],
            ['module' => 'GeneralServices', 'name' => 'vehicles.dispatch',    'description' => 'Assign driver and vehicle to a vehicle request before Division Chief approval'],
            ['module' => 'GeneralServices', 'name' => 'vehicles.dc-approve',  'description' => 'Approve vehicle requests as Division Chief'],
            ['module' => 'GeneralServices', 'name' => 'vehicles.ocd-approve', 'description' => 'Approve vehicle requests as OCD'],

            // ── General Services — Facilities / Work / Service ───────────────
            ['module' => 'GeneralServices', 'name' => 'facilities.view',        'description' => 'View facility, work and service requests'],
            ['module' => 'GeneralServices', 'name' => 'facilities.create',      'description' => 'File facility, work and service requests'],
            ['module' => 'GeneralServices', 'name' => 'facilities.manage',      'description' => 'Manage facilities, assets and request lifecycle'],
            ['module' => 'GeneralServices', 'name' => 'facilities.dc-approve',  'description' => 'Approve facility/work/service requests as Division Chief'],
            ['module' => 'GeneralServices', 'name' => 'facilities.fad-approve', 'description' => 'Approve facility/work/service requests as FAD Chief'],
            ['module' => 'GeneralServices', 'name' => 'facilities.ocd-approve', 'description' => 'Approve facility requests as OCD'],


            // ── Reports ──────────────────────────────────────────────────────
            ['module' => 'Reports', 'name' => 'reports.view',   'description' => 'Access the Reports module'],
            ['module' => 'Reports', 'name' => 'reports.export', 'description' => 'Export reports (PDF/Excel)'],

            // ── Chat ─────────────────────────────────────────────────────────
            ['module' => 'Chat', 'name' => 'chat.access', 'description' => 'Use the campus messenger'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name']],
                ['module' => $perm['module'], 'description' => $perm['description']],
            );
        }

        $this->command?->info('Permissions seeded: ' . count($permissions));

        // Org Structure permissions live in their own seeder (shared catalog).
        $this->call(OrgStructurePermissionsSeeder::class);
    }
}
