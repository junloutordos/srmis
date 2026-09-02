<?php

namespace Tests\Feature\Approvals;

use App\Models\Division;
use App\Models\ITJobRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\VehicleRequest;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * HTTP-level tests for ApprovalInboxController.
 *
 * @see \.kiro\specs\unified-approvals-inbox\tasks.md Task 8.3
 */
class ApprovalInboxControllerTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function userWithRole(?string $roleName = null, array $userAttrs = []): User
    {
        $user = User::factory()->create($userAttrs);

        if ($roleName) {
            $role = Role::firstOrCreate(['name' => $roleName]);

            // A couple of delegated per-module controller methods gate on a
            // specific permission (e.g. VehicleRequestController::declineInApp
            // requires vehicles.dc-approve) in addition to the inbox's own
            // authorisation check, so make sure the role carries it — mirrors
            // what TenantRolePermissionSeeder does for a real tenant.
            if ($roleName === 'DivisionChief') {
                $perm = \App\Models\Permission::firstOrCreate(
                    ['name' => 'vehicles.dc-approve'],
                    ['module' => 'GeneralServices', 'description' => 'Approve vehicle requests as Division Chief']
                );
                $role->permissions()->syncWithoutDetaching([$perm->id]);
            }

            $user->roles()->attach($role->id);
        }

        return $user;
    }

    // ── index() ──────────────────────────────────────────────────────────────

    public function test_index_returns_403_for_user_with_no_approver_role(): void
    {
        $user = $this->userWithRole('Staff');

        $this->actingAs($user)
            ->get(route('approvals.inbox'))
            ->assertForbidden();
    }

    public function test_index_returns_200_for_division_chief(): void
    {
        $chief = $this->userWithRole('DivisionChief');

        $this->actingAs($chief)
            ->get(route('approvals.inbox'))
            ->assertOk();
    }

    // ── approve() ────────────────────────────────────────────────────────────

    public function test_approve_returns_404_for_unknown_type_slug(): void
    {
        $chief = $this->userWithRole('DivisionChief');

        $this->actingAs($chief)
            ->post(route('approvals.approve', ['type' => 'not_a_real_type', 'id' => 1]))
            ->assertNotFound();
    }

    public function test_approve_returns_404_when_id_does_not_exist(): void
    {
        $chief = $this->userWithRole('DivisionChief');

        // The controller wraps abort(404) in a try/catch and redirects back
        // with a flashed error instead of a raw 404 response — a bare 404
        // lacks the X-Inertia header the frontend needs to resolve its
        // "processing…" state, so this redirect *is* the "not found" outcome.
        $response = $this->actingAs($chief)
            ->post(route('approvals.approve', ['type' => 'it_job_requests', 'id' => 999999]));

        $response->assertRedirect();
        $response->assertSessionHasErrors('message');
    }

    public function test_approve_redirects_with_error_when_record_already_acted_upon(): void
    {
        $chief = $this->userWithRole('DivisionChief');
        $requester = User::factory()->create();

        // Already approved — no longer in a pending state for this type.
        $jobRequest = ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'Done',
            'description' => 'x', 'status' => 'Completed', 'divisionchief_id' => $chief->id,
        ]);

        $response = $this->actingAs($chief)
            ->post(route('approvals.approve', ['type' => 'it_job_requests', 'id' => $jobRequest->id]));

        $response->assertRedirect();
        $response->assertSessionHasErrors('message');
    }

    public function test_approve_redirects_with_error_when_user_is_not_the_assigned_approver(): void
    {
        $chiefA = $this->userWithRole('DivisionChief');
        $chiefB = $this->userWithRole('DivisionChief');
        $requester = User::factory()->create();

        $jobRequest = ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'For chief B',
            'description' => 'x', 'status' => 'Pending Division Chief Approval', 'divisionchief_id' => $chiefB->id,
        ]);

        // Chief A is a DivisionChief, but not the one assigned to this request,
        // and division lookup will find no matching division either.
        $response = $this->actingAs($chiefA)
            ->post(route('approvals.approve', ['type' => 'it_job_requests', 'id' => $jobRequest->id]));

        $response->assertRedirect();
        $response->assertSessionHasErrors('message');

        $this->assertSame('Pending Division Chief Approval', $jobRequest->refresh()->status);
    }

    public function test_approve_succeeds_for_the_assigned_division_chief(): void
    {
        $chief = $this->userWithRole('DivisionChief');
        $requester = User::factory()->create();

        $jobRequest = ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'For me',
            'description' => 'x', 'status' => 'Pending Division Chief Approval', 'divisionchief_id' => $chief->id,
        ]);

        $response = $this->actingAs($chief)
            ->post(route('approvals.approve', ['type' => 'it_job_requests', 'id' => $jobRequest->id]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('Pending OCD Approval', $jobRequest->refresh()->status);
    }

    // ── decline() ────────────────────────────────────────────────────────────

    public function test_decline_requires_a_reason(): void
    {
        $chief = $this->userWithRole('DivisionChief');
        $requester = User::factory()->create();

        $jobRequest = ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'For me',
            'description' => 'x', 'status' => 'Pending Division Chief Approval', 'divisionchief_id' => $chief->id,
        ]);

        $response = $this->actingAs($chief)
            ->post(route('approvals.decline', ['type' => 'it_job_requests', 'id' => $jobRequest->id]), []);

        $response->assertSessionHasErrors('reason');
    }

    public function test_decline_redirects_with_error_on_already_acted_record(): void
    {
        $chief = $this->userWithRole('DivisionChief');
        $requester = User::factory()->create();

        $jobRequest = ITJobRequest::create([
            'user_id' => $requester->id, 'category' => 'Hardware', 'title' => 'Done',
            'description' => 'x', 'status' => 'Completed', 'divisionchief_id' => $chief->id,
        ]);

        $response = $this->actingAs($chief)
            ->post(route('approvals.decline', ['type' => 'it_job_requests', 'id' => $jobRequest->id]), [
                'reason' => 'No longer needed',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('message');
    }

    public function test_decline_succeeds_for_the_assigned_division_chief_with_a_vehicle_request(): void
    {
        $chief = $this->userWithRole('DivisionChief');
        $requester = User::factory()->create();

        $vehicleRequest = VehicleRequest::create([
            'requestor_id' => $requester->id, 'purpose' => 'Field trip', 'destination' => 'Manila',
            'date_needed' => now()->addDay(), 'passengers' => 2,
            'division_chief_id' => $chief->id, 'status' => 'Pending Division Chief Approval',
        ]);

        $response = $this->actingAs($chief)
            ->post(route('approvals.decline', ['type' => 'vehicle_requests', 'id' => $vehicleRequest->id]), [
                'reason' => 'No vehicle available',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSame('Declined', $vehicleRequest->refresh()->status);
    }
}
