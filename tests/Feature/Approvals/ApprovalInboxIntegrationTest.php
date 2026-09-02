<?php

namespace Tests\Feature\Approvals;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalInboxService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * Integration / smoke tests for the Unified Approvals Inbox feature.
 *
 * @see \.kiro\specs\unified-approvals-inbox\tasks.md Task 9
 */
class ApprovalInboxIntegrationTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function userWithPermission(string $permissionName, array $userAttrs = []): User
    {
        $user = User::factory()->create($userAttrs);

        $role = Role::firstOrCreate(['name' => 'TestRole_' . $permissionName]);
        $perm = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'test', 'description' => $permissionName]
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function userWithRole(string $roleName, array $userAttrs = []): User
    {
        $user = User::factory()->create($userAttrs);
        $role = Role::firstOrCreate(['name' => $roleName]);
        $user->roles()->attach($role->id);

        return $user;
    }

    // ── 9.1 — existing per-module approval routes still work ───────────────────

    public function test_it_job_requests_for_approval_route_still_returns_200(): void
    {
        // The route middleware gates on it.requests.manage, but the controller
        // itself further restricts forApproval() to DivisionChief /
        // InformationOfficer roles — both must be satisfied.
        $user = $this->userWithPermission('it.requests.manage');
        $role = Role::firstOrCreate(['name' => 'DivisionChief']);
        $user->roles()->attach($role->id);
        $user->clearPermissionCache();

        $this->actingAs($user)
            ->get(route('job-requests.for-approval'))
            ->assertOk();
    }

    public function test_it_job_requests_ocd_approval_route_still_returns_200(): void
    {
        $user = $this->userWithPermission('it.requests.manage');

        $this->actingAs($user)
            ->get(route('job-requests.ocd-approval'))
            ->assertOk();
    }

    public function test_vehicle_requests_dc_approval_route_still_returns_200(): void
    {
        $user = $this->userWithPermission('vehicles.dc-approve');

        $this->actingAs($user)
            ->get(route('vehicle-requests.dc-approval'))
            ->assertOk();
    }

    public function test_vehicle_requests_ocd_approval_route_still_returns_200(): void
    {
        $user = $this->userWithPermission('vehicles.ocd-approve');

        $this->actingAs($user)
            ->get(route('vehicle-requests.ocd-approval'))
            ->assertOk();
    }

    // Note: the original task text also references `gatepass.ocd-approval` and
    // `messengerial.for-approval` routes. Neither exists in this codebase —
    // the Gate Pass and Messengerial Request modules were dropped from SRMIS
    // during extraction from the CRCMIS monolith (see
    // database/migrations/tenant/2026_06_12_000004_drop_messengerial_and_assets.php
    // and AGENTS.md). Skipped as not applicable.

    // ── 9.2 — approvalInboxCount shared prop is present ─────────────────────────

    public function test_approval_inbox_count_shared_prop_is_present_for_approver_users(): void
    {
        $chief = $this->userWithRole('DivisionChief');

        $response = $this->actingAs($chief)->get(route('approvals.inbox'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->has('approvalInboxCount'));
    }

    // ── 9.3 — index() query count stays bounded ─────────────────────────────────

    public function test_index_query_count_is_reasonably_bounded_for_each_role(): void
    {
        $roles = ['DivisionChief', 'GSU Head', 'OCD'];

        foreach ($roles as $roleName) {
            $user = $this->userWithRole($roleName);

            DB::connection()->enableQueryLog();
            DB::connection()->flushQueryLog();

            (new ApprovalInboxService($user))->getPendingItems();

            $queryCount = count(DB::connection()->getQueryLog());
            DB::connection()->disableQueryLog();

            // The service issues one query per module tab (5 modules) plus a
            // couple of division-lookup queries — comfortably bounded, but not
            // as tight as the original 10-table Gate-Pass/Leave design assumed.
            $this->assertLessThanOrEqual(
                20,
                $queryCount,
                "ApprovalInboxService::getPendingItems() issued {$queryCount} queries for role {$roleName}, expected <= 20."
            );
        }
    }
}
