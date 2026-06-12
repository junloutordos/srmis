<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

/**
 * Org Structure permission catalog (Data Management module).
 * Role assignments live in TenantRolePermissionSeeder.
 */
class OrgStructurePermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['module' => 'OrgStructure', 'name' => 'org.view',
             'description' => 'View the organizational chart and unit details'],
            ['module' => 'OrgStructure', 'name' => 'org.view_all',
             'description' => 'View the full org chart including inactive units'],

            ['module' => 'OrgStructure', 'name' => 'org.units.create',
             'description' => 'Create new organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.units.update',
             'description' => 'Edit existing organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.units.delete',
             'description' => 'Delete / archive organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.units.manage',
             'description' => 'Full CRUD management of organizational units'],

            ['module' => 'OrgStructure', 'name' => 'org.assign',
             'description' => 'Assign or re-assign employees to organizational units'],
            ['module' => 'OrgStructure', 'name' => 'org.assign.manage',
             'description' => 'Full management of employee unit assignments'],

            ['module' => 'OrgStructure', 'name' => 'org.heads.manage',
             'description' => 'Designate and manage unit heads'],

            ['module' => 'OrgStructure', 'name' => 'org.versions.view',
             'description' => 'View org structure version history'],
            ['module' => 'OrgStructure', 'name' => 'org.versions.manage',
             'description' => 'Create, approve, and activate org structure versions'],

            ['module' => 'OrgStructure', 'name' => 'org.export',
             'description' => 'Export the organizational chart (PDF, PNG, Excel)'],
            ['module' => 'OrgStructure', 'name' => 'org.reports',
             'description' => 'Generate organizational structure reports'],
        ];

        foreach ($permissions as $perm) {
            Permission::updateOrCreate(
                ['name' => $perm['name']],
                ['module' => $perm['module'], 'description' => $perm['description']],
            );
        }

        $this->command?->info('Org Structure permissions seeded: ' . count($permissions));
    }
}
