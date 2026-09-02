<?php

namespace Tests\Feature\Approvals;

use App\Models\Division;
use App\Models\FacilityRequest;
use App\Models\ITJobRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\VehicleRequest;
use App\Models\WorkRequest;
use App\Services\ApprovalInboxService;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * Unit tests for ApprovalInboxService's role-scoped query logic.
 *
 * @see \.kiro\specs\unified-approvals-inbox\tasks.md Task 8.1
 */
class ApprovalInboxServiceTest extends TestCase
{
    use RefreshesTenantDatabase;

    /** Create a user, optionally attaching a role by name (created if missing). */
    protected function userWithRole(?string $roleName = null, array $userAttrs = []): User
    {
        $user = User::factory()->create($userAttrs);

        if ($roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    protected function makeDivision(User $chief): Division
    {
        return Division::create([
            'division_name'     => 'Division of ' . $chief->id,
            'acronym'           => 'D' . $chief->id,
            'division_chief_id' => $chief->id,
            'status'            => 'active',
        ]);
    }

    // ── Division Chief ───────────────────────────────────────────────────────

    public function test_division_chief_only_receives_dc_pending_statuses_across_modules(): void
    {
        $chief = $this->userWithRole('DivisionChief');
        $division = $this->makeDivision($chief);
        $requester = User::factory()->create(['division_id' => $division->id]);

        // IT Job Request — pending DC (visible) vs. pending OCD (not visible to DC)
        $pendingDcItjr = ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'Broken monitor',
            'description' => 'x', 'status' => 'Pending Division Chief Approval', 'divisionchief_id' => $chief->id,
        ]);
        ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'Not for this chief',
            'description' => 'x', 'status' => 'Pending OCD Approval', 'divisionchief_id' => $chief->id,
        ]);

        // Vehicle Request — pending DC (visible) vs. approved (not visible to DC)
        $pendingDcVr = VehicleRequest::create([
            'requestor_id' => $requester->id, 'purpose' => 'Field trip', 'destination' => 'Manila',
            'date_needed' => now()->addDay(), 'passengers' => 2,
            'division_chief_id' => $chief->id, 'status' => 'Pending Division Chief Approval',
        ]);
        VehicleRequest::create([
            'requestor_id' => $requester->id, 'purpose' => 'Already approved', 'destination' => 'Manila',
            'date_needed' => now()->addDay(), 'passengers' => 2,
            'division_chief_id' => $chief->id, 'status' => 'Approved',
        ]);

        // Facility Request — pending (visible, requester in DC's division) vs. FAD-stage (not visible)
        $pendingFr = FacilityRequest::create([
            'requestor_id' => $requester->id, 'activity' => 'Seminar', 'purpose' => 'Training',
            'date_start' => now()->addDay()->format('Y-m-d'), 'status' => 'Pending',
        ]);
        FacilityRequest::create([
            'requestor_id' => $requester->id, 'activity' => 'Past DC stage', 'purpose' => 'Training',
            'date_start' => now()->addDay()->format('Y-m-d'), 'status' => 'Pending FAD Approval',
        ]);

        // Work Request — pending, division_chief_id matches (visible) vs GSU Approved (not visible)
        $pendingWr = WorkRequest::create([
            'issue' => 'Leaky faucet', 'category' => 'Plumbing', 'requester_id' => $requester->id,
            'division_chief_id' => $chief->id, 'status' => 'Pending',
        ]);
        WorkRequest::create([
            'issue' => 'Already with GSU', 'category' => 'Electrical', 'requester_id' => $requester->id,
            'division_chief_id' => $chief->id, 'status' => 'GSU Approved',
        ]);

        // Service Request — pending (requester in division, visible) vs. approved (not visible)
        $pendingSr = ServiceRequest::create([
            'service_type' => 'Photocopy', 'requestor_id' => $requester->id, 'status' => 'Pending', 'date_needed' => now()->addDay()->format('Y-m-d'),
        ]);
        ServiceRequest::create([
            'service_type' => 'Already approved', 'requestor_id' => $requester->id, 'status' => 'Approved', 'date_needed' => now()->addDay()->format('Y-m-d'),
        ]);

        $tabs = (new ApprovalInboxService($chief))->getPendingItems();
        $byType = collect($tabs)->keyBy('type');

        $this->assertSame(1, $byType['it_job_requests']['count']);
        $this->assertSame($pendingDcItjr->id, $byType['it_job_requests']['items'][0]['id']);

        $this->assertSame(1, $byType['vehicle_requests']['count']);
        $this->assertSame($pendingDcVr->id, $byType['vehicle_requests']['items'][0]['id']);

        $this->assertSame(1, $byType['facility_requests']['count']);
        $this->assertSame($pendingFr->id, $byType['facility_requests']['items'][0]['id']);

        $this->assertSame(1, $byType['work_requests']['count']);
        $this->assertSame($pendingWr->id, $byType['work_requests']['items'][0]['id']);

        $this->assertSame(1, $byType['service_requests']['count']);
        $this->assertSame($pendingSr->id, $byType['service_requests']['items'][0]['id']);
    }

    public function test_division_chief_does_not_see_another_chiefs_requests(): void
    {
        $chiefA = $this->userWithRole('DivisionChief');
        $chiefB = $this->userWithRole('DivisionChief');
        $divisionB = $this->makeDivision($chiefB);
        $requesterB = User::factory()->create(['division_id' => $divisionB->id]);

        ITJobRequest::create([
            'user_id' => $requesterB->id, 'category' => 'Hardware', 'title' => 'For chief B',
            'description' => 'x', 'status' => 'Pending Division Chief Approval', 'divisionchief_id' => $chiefB->id,
        ]);

        $tabs = (new ApprovalInboxService($chiefA))->getPendingItems();

        $this->assertEmpty($tabs, 'Chief A should not see items scoped to Chief B');
    }

    // ── FAD Chief ─────────────────────────────────────────────────────────────

    public function test_fad_chief_only_receives_fad_stage_items(): void
    {
        $fadChief = $this->userWithRole(null, ['position' => 'FAD Chief']);
        $requester = User::factory()->create();

        $pendingFr = FacilityRequest::create([
            'requestor_id' => $requester->id, 'activity' => 'For FAD', 'purpose' => 'x',
            'date_start' => now()->format('Y-m-d'), 'status' => 'Pending FAD Approval',
        ]);
        FacilityRequest::create([
            'requestor_id' => $requester->id, 'activity' => 'Not yet at FAD', 'purpose' => 'x',
            'date_start' => now()->format('Y-m-d'), 'status' => 'Pending',
        ]);

        $pendingWr = WorkRequest::create([
            'issue' => 'For FAD', 'category' => 'Plumbing', 'requester_id' => $requester->id,
            'status' => 'GSU Approved',
        ]);
        WorkRequest::create([
            'issue' => 'Not yet with GSU', 'category' => 'Plumbing', 'requester_id' => $requester->id,
            'status' => 'Pending',
        ]);

        $pendingSr = ServiceRequest::create([
            'service_type' => 'For FAD', 'requestor_id' => $requester->id, 'status' => 'Approved', 'date_needed' => now()->addDay()->format('Y-m-d'),
        ]);
        ServiceRequest::create([
            'service_type' => 'Not yet approved by DC', 'requestor_id' => $requester->id, 'status' => 'Pending', 'date_needed' => now()->addDay()->format('Y-m-d'),
        ]);

        $tabs = (new ApprovalInboxService($fadChief))->getPendingItems();
        $byType = collect($tabs)->keyBy('type');

        $this->assertArrayNotHasKey('it_job_requests', $byType);
        $this->assertArrayNotHasKey('vehicle_requests', $byType);

        $this->assertSame(1, $byType['facility_requests']['count']);
        $this->assertSame($pendingFr->id, $byType['facility_requests']['items'][0]['id']);

        $this->assertSame(1, $byType['work_requests']['count']);
        $this->assertSame($pendingWr->id, $byType['work_requests']['items'][0]['id']);

        $this->assertSame(1, $byType['service_requests']['count']);
        $this->assertSame($pendingSr->id, $byType['service_requests']['items'][0]['id']);
    }

    // ── GSU Head ──────────────────────────────────────────────────────────────

    public function test_gsu_head_only_receives_gsu_stage_items(): void
    {
        $gsuHead = $this->userWithRole('GSU Head');
        $requester = User::factory()->create();

        // Vehicle requests are intentionally excluded from the inbox for GSU —
        // they're handled on the dedicated dispatch page.
        VehicleRequest::create([
            'requestor_id' => $requester->id, 'purpose' => 'Should not appear', 'destination' => 'x',
            'date_needed' => now()->addDay(), 'passengers' => 1, 'status' => 'Pending GSU Assignment',
        ]);

        $pendingFr = FacilityRequest::create([
            'requestor_id' => $requester->id, 'activity' => 'For GSU', 'purpose' => 'x',
            'date_start' => now()->format('Y-m-d'), 'status' => 'Pending FAD Approval',
        ]);
        FacilityRequest::create([
            'requestor_id' => $requester->id, 'activity' => 'Not for GSU', 'purpose' => 'x',
            'date_start' => now()->format('Y-m-d'), 'status' => 'Pending',
        ]);

        $pendingWr = WorkRequest::create([
            'issue' => 'For GSU', 'category' => 'Plumbing', 'requester_id' => $requester->id,
            'status' => 'GSU Approved',
        ]);
        WorkRequest::create([
            'issue' => 'Not for GSU', 'category' => 'Plumbing', 'requester_id' => $requester->id,
            'status' => 'Pending',
        ]);

        $tabs = (new ApprovalInboxService($gsuHead))->getPendingItems();
        $byType = collect($tabs)->keyBy('type');

        $this->assertArrayNotHasKey('vehicle_requests', $byType, 'GSU dispatch items must not appear in the inbox');

        $this->assertSame(1, $byType['facility_requests']['count']);
        $this->assertSame($pendingFr->id, $byType['facility_requests']['items'][0]['id']);

        $this->assertSame(1, $byType['work_requests']['count']);
        $this->assertSame($pendingWr->id, $byType['work_requests']['items'][0]['id']);
    }

    public function test_gsu_dispatcher_role_is_treated_like_gsu_head(): void
    {
        $dispatcher = $this->userWithRole('GSU Dispatcher');
        $requester = User::factory()->create();

        $pendingFr = FacilityRequest::create([
            'requestor_id' => $requester->id, 'activity' => 'For GSU', 'purpose' => 'x',
            'date_start' => now()->format('Y-m-d'), 'status' => 'Pending FAD Approval',
        ]);

        $tabs = (new ApprovalInboxService($dispatcher))->getPendingItems();
        $byType = collect($tabs)->keyBy('type');

        $this->assertSame(1, $byType['facility_requests']['count']);
        $this->assertSame($pendingFr->id, $byType['facility_requests']['items'][0]['id']);
    }

    // ── OCD ───────────────────────────────────────────────────────────────────

    public function test_ocd_only_receives_ocd_stage_items(): void
    {
        $ocd = $this->userWithRole('OCD');
        $requester = User::factory()->create();

        $pendingItjr = ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'For OCD',
            'description' => 'x', 'status' => 'Pending OCD Approval', 'divisionchief_id' => $ocd->id,
        ]);
        ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'Not yet at OCD',
            'description' => 'x', 'status' => 'Pending Division Chief Approval', 'divisionchief_id' => $ocd->id,
        ]);

        $pendingVr = VehicleRequest::create([
            'requestor_id' => $requester->id, 'purpose' => 'For OCD', 'destination' => 'x',
            'date_needed' => now()->addDay(), 'passengers' => 1, 'status' => 'Approved',
        ]);
        VehicleRequest::create([
            'requestor_id' => $requester->id, 'purpose' => 'Not yet at OCD', 'destination' => 'x',
            'date_needed' => now()->addDay(), 'passengers' => 1, 'status' => 'Pending Division Chief Approval',
        ]);

        $pendingFr = FacilityRequest::create([
            'requestor_id' => $requester->id, 'activity' => 'For OCD', 'purpose' => 'x',
            'date_start' => now()->format('Y-m-d'), 'status' => 'Pending OCD Approval',
        ]);
        FacilityRequest::create([
            'requestor_id' => $requester->id, 'activity' => 'Not yet at OCD', 'purpose' => 'x',
            'date_start' => now()->format('Y-m-d'), 'status' => 'Pending FAD Approval',
        ]);

        $tabs = (new ApprovalInboxService($ocd))->getPendingItems();
        $byType = collect($tabs)->keyBy('type');

        $this->assertSame(1, $byType['it_job_requests']['count']);
        $this->assertSame($pendingItjr->id, $byType['it_job_requests']['items'][0]['id']);

        $this->assertSame(1, $byType['vehicle_requests']['count']);
        $this->assertSame($pendingVr->id, $byType['vehicle_requests']['items'][0]['id']);

        $this->assertSame(1, $byType['facility_requests']['count']);
        $this->assertSame($pendingFr->id, $byType['facility_requests']['items'][0]['id']);

        $this->assertArrayNotHasKey('work_requests', $byType);
    }

    // ── No role ───────────────────────────────────────────────────────────────

    public function test_user_with_no_approver_role_receives_no_tabs(): void
    {
        $user = $this->userWithRole('Staff');
        $requester = User::factory()->create();

        ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'x',
            'description' => 'x', 'status' => 'Pending Division Chief Approval', 'divisionchief_id' => $requester->id,
        ]);

        $tabs = (new ApprovalInboxService($user))->getPendingItems();

        $this->assertEmpty($tabs);
        $this->assertSame(0, (new ApprovalInboxService($user))->totalPendingCount());
    }

    // ── Administrator (union of all roles) ───────────────────────────────────

    public function test_administrator_sees_items_across_all_roles(): void
    {
        $admin = $this->userWithRole('Administrator');
        $requester = User::factory()->create();

        ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'DC stage',
            'description' => 'x', 'status' => 'Pending Division Chief Approval', 'divisionchief_id' => $admin->id,
        ]);
        ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'OCD stage',
            'description' => 'x', 'status' => 'Pending OCD Approval', 'divisionchief_id' => $admin->id,
        ]);

        $tabs = (new ApprovalInboxService($admin))->getPendingItems();
        $byType = collect($tabs)->keyBy('type');

        $this->assertSame(2, $byType['it_job_requests']['count']);
    }
}
