<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Role → permission map for SRMIS, applied per tenant.
 *
 * Grants follow least privilege: approvers only receive the approval
 * permission for their own stage, and management permissions are limited to
 * the role that operationally owns the module.
 */
class TenantRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $assign = function (string $roleName, array $permissionNames): void {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                return;
            }
            $ids = Permission::whereIn('name', $permissionNames)->pluck('id')->toArray();
            $role->permissions()->syncWithoutDetaching($ids);
        };

        // ── Administrator — everything (isSuperAdmin() bypasses anyway) ──────
        $admin = Role::where('name', 'Administrator')->first();
        if ($admin) {
            $admin->permissions()->sync(Permission::pluck('id')->toArray());
        }

        // ── Baseline for every employee-type role ────────────────────────────
        $baseline = [
            'it.requests.view',     // file/track own IT job requests
            'vehicles.view',        // file/track own vehicle requests
            'facilities.view',      // view facility/work/service requests
            'facilities.create',    // file facility/work/service requests
            'chat.access',
            'org.view',
        ];
        foreach (['MIS', 'ITJR Dispatcher', 'DivisionChief', 'FAD Chief', 'GSU Head', 'GSU Dispatcher', 'OCD', 'Faculty', 'Staff', 'Driver'] as $role) {
            $assign($role, $baseline);
        }

        // ── MIS — owns the MIS module + reports ──────────────────────────────
        $assign('MIS', [
            'it.requests.manage',
            'it.equipment.view', 'it.equipment.manage',
            'users.view',
            'reports.view', 'reports.export',
        ]);

        // ── ITJR Dispatcher — dispatches OCD-approved requests to MIS ────────
        $assign('ITJR Dispatcher', [
            'it.requests.dispatch',
        ]);

        // ── Division Chief — first-stage approvals ───────────────────────────
        $assign('DivisionChief', [
            'vehicles.dc-approve',
            'facilities.dc-approve',
            'org.view_all', 'org.export', 'org.reports', 'org.versions.view',
        ]);

        // ── FAD Chief — second-stage facility/work/service approvals ─────────
        $assign('FAD Chief', [
            'facilities.fad-approve',
            'org.view_all', 'org.export', 'org.reports', 'org.versions.view',
        ]);

        // ── GSU Head — operations: vehicles, facilities, assets, completion ──
        $assign('GSU Head', [
            'vehicles.manage',
            'vehicles.dispatch',
            'facilities.manage',
        ]);

        // ── GSU Dispatcher — assigns driver + vehicle before DC approval ─────
        $assign('GSU Dispatcher', [
            'vehicles.dispatch',
        ]);

        // ── OCD — final-stage approvals ──────────────────────────────────────
        $assign('OCD', [
            'vehicles.ocd-approve',
            'facilities.ocd-approve',
            'org.view_all', 'org.export', 'org.reports', 'org.versions.view',
        ]);


        $this->command?->info('Role permissions mapped.');
    }
}
