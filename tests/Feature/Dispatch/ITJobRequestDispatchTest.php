<?php

namespace Tests\Feature\Dispatch;

use App\Models\Division;
use App\Models\ITJobCategory;
use App\Models\ITJobRequest;
use App\Models\ITJRTrackingLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * Tests for the IT Job Request dispatch workflow:
 * OCD approval -> 'Pending Dispatch' -> ITJR Dispatcher assigns MIS personnel
 * -> 'In Progress'.
 */
class ITJobRequestDispatchTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function userWithRoleAndPermission(string $roleName, string $permissionName, array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $role = Role::firstOrCreate(['name' => $roleName]);
        $perm = Permission::firstOrCreate(
            ['name' => $permissionName],
            ['module' => 'MIS', 'description' => $permissionName]
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function misUser(array $attrs = []): User
    {
        $user = User::factory()->create($attrs);
        $role = Role::firstOrCreate(['name' => 'MIS']);
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function pendingDispatchRequest(array $overrides = []): ITJobRequest
    {
        $requester = User::factory()->create();
        $chief = User::factory()->create();

        return ITJobRequest::create(array_merge([
            'user_id'          => $requester->id,
            'category'         => 'Hardware',
            'title'            => 'Broken monitor',
            'description'      => 'Screen flickers',
            'status'           => 'Pending Dispatch',
            'divisionchief_id' => $chief->id,
            'priority'         => 'normal',
            'queued_at'        => now(),
        ], $overrides));
    }

    // ── dispatchQueue() ──────────────────────────────────────────────────────

    public function test_dispatch_queue_returns_403_without_permission(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('jobrequests.dispatch'))
            ->assertForbidden();
    }

    public function test_dispatch_queue_returns_200_with_permission(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('ITJR Dispatcher', 'it.requests.dispatch');

        $this->actingAs($dispatcher)
            ->get(route('jobrequests.dispatch'))
            ->assertOk();
    }

    public function test_dispatch_queue_only_lists_pending_dispatch_requests(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('ITJR Dispatcher', 'it.requests.dispatch');

        $pending = $this->pendingDispatchRequest(['title' => 'Should appear']);
        $this->pendingDispatchRequest(['title' => 'Still with OCD', 'status' => 'Pending OCD Approval']);
        $this->pendingDispatchRequest(['title' => 'Already in progress', 'status' => 'In Progress']);

        $response = $this->actingAs($dispatcher)->get(route('jobrequests.dispatch'));

        $response->assertOk();
        $response->assertInertia(function ($page) use ($pending) {
            $items = $page->toArray()['props']['items'];
            $this->assertCount(1, $items);
            $this->assertSame($pending->id, $items[0]['id']);
            return true;
        });
    }

    public function test_dispatch_queue_orders_by_priority_then_queued_at(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('ITJR Dispatcher', 'it.requests.dispatch');

        $low = $this->pendingDispatchRequest(['title' => 'Low', 'priority' => 'low', 'queued_at' => now()->subMinutes(1)]);
        $urgent = $this->pendingDispatchRequest(['title' => 'Urgent', 'priority' => 'urgent', 'queued_at' => now()]);
        $high = $this->pendingDispatchRequest(['title' => 'High', 'priority' => 'high', 'queued_at' => now()->subMinutes(2)]);

        $response = $this->actingAs($dispatcher)->get(route('jobrequests.dispatch'));

        $response->assertInertia(function ($page) use ($urgent, $high, $low) {
            $items = $page->toArray()['props']['items'];
            $this->assertSame([$urgent->id, $high->id, $low->id], array_column($items, 'id'));
            return true;
        });
    }

    public function test_dispatch_queue_filters_by_search_and_category(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('ITJR Dispatcher', 'it.requests.dispatch');

        $match = $this->pendingDispatchRequest(['title' => 'Projector not turning on', 'category' => 'Hardware']);
        $this->pendingDispatchRequest(['title' => 'Network issue', 'category' => 'Network']);

        $response = $this->actingAs($dispatcher)
            ->get(route('jobrequests.dispatch', ['search' => 'projector']));

        $response->assertInertia(function ($page) use ($match) {
            $items = $page->toArray()['props']['items'];
            $this->assertCount(1, $items);
            $this->assertSame($match->id, $items[0]['id']);
            return true;
        });
    }

    // ── dispatchToMis() ──────────────────────────────────────────────────────

    public function test_dispatch_to_mis_returns_403_without_permission(): void
    {
        $user = User::factory()->create();
        $jobRequest = $this->pendingDispatchRequest();
        $mis = $this->misUser();

        $this->actingAs($user)
            ->post(route('jobrequests.dispatch.action', $jobRequest->id), ['assignedto' => $mis->id])
            ->assertForbidden();
    }

    public function test_dispatch_to_mis_assigns_and_transitions_to_in_progress(): void
    {
        Mail::fake();
        $dispatcher = $this->userWithRoleAndPermission('ITJR Dispatcher', 'it.requests.dispatch');
        $jobRequest = $this->pendingDispatchRequest();
        $mis = $this->misUser();

        $response = $this->actingAs($dispatcher)
            ->post(route('jobrequests.dispatch.action', $jobRequest->id), [
                'assignedto' => $mis->id,
                'notes'      => 'Validated with the requester — hardware issue, routine.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $jobRequest->refresh();
        $this->assertSame('In Progress', $jobRequest->status);
        $this->assertSame($mis->id, $jobRequest->assignedto);
        $this->assertNotNull($jobRequest->queued_at);

        $this->assertDatabaseHas('itjr_tracking_logs', [
            'it_job_request_id' => $jobRequest->id,
            'status'            => 'Dispatched to MIS',
        ]);
        $log = ITJRTrackingLog::where('it_job_request_id', $jobRequest->id)
            ->where('status', 'Dispatched to MIS')->first();
        $this->assertStringContainsString('Validated with the requester', $log->remarks);
    }

    public function test_dispatch_to_mis_requires_a_triage_note_on_first_dispatch(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('ITJR Dispatcher', 'it.requests.dispatch');
        $jobRequest = $this->pendingDispatchRequest();
        $mis = $this->misUser();

        $response = $this->actingAs($dispatcher)
            ->post(route('jobrequests.dispatch.action', $jobRequest->id), ['assignedto' => $mis->id]);

        $response->assertSessionHasErrors('notes');
        $this->assertSame('Pending Dispatch', $jobRequest->refresh()->status);
    }

    public function test_dispatch_to_mis_does_not_require_a_note_on_reassignment(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('ITJR Dispatcher', 'it.requests.dispatch');
        $firstMis = $this->misUser();
        $secondMis = $this->misUser();

        $jobRequest = $this->pendingDispatchRequest([
            'status' => 'In Progress', 'assignedto' => $firstMis->id, 'queued_at' => now(),
        ]);

        $response = $this->actingAs($dispatcher)
            ->post(route('jobrequests.dispatch.action', $jobRequest->id), ['assignedto' => $secondMis->id]);

        $response->assertSessionHasNoErrors();
    }

    public function test_dispatch_to_mis_rejects_a_non_mis_assignee(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('ITJR Dispatcher', 'it.requests.dispatch');
        $jobRequest = $this->pendingDispatchRequest();
        $notMis = User::factory()->create();

        $response = $this->actingAs($dispatcher)
            ->post(route('jobrequests.dispatch.action', $jobRequest->id), [
                'assignedto' => $notMis->id,
                'notes'      => 'Triage note.',
            ]);

        $response->assertSessionHasErrors('assignedto');
        $this->assertSame('Pending Dispatch', $jobRequest->refresh()->status);
    }

    public function test_dispatch_to_mis_requires_an_assignee(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('ITJR Dispatcher', 'it.requests.dispatch');
        $jobRequest = $this->pendingDispatchRequest();

        $response = $this->actingAs($dispatcher)
            ->post(route('jobrequests.dispatch.action', $jobRequest->id), []);

        $response->assertSessionHasErrors('assignedto');
    }

    public function test_dispatch_to_mis_rejects_a_request_not_awaiting_dispatch(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('ITJR Dispatcher', 'it.requests.dispatch');
        $jobRequest = $this->pendingDispatchRequest(['status' => 'Pending OCD Approval']);
        $mis = $this->misUser();

        $response = $this->actingAs($dispatcher)
            ->post(route('jobrequests.dispatch.action', $jobRequest->id), ['assignedto' => $mis->id]);

        $response->assertSessionHasErrors('message');
        $this->assertSame('Pending OCD Approval', $jobRequest->refresh()->status);
    }

    public function test_dispatch_to_mis_allows_reassignment_while_in_progress(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('ITJR Dispatcher', 'it.requests.dispatch');
        $firstMis = $this->misUser();
        $secondMis = $this->misUser();

        $jobRequest = $this->pendingDispatchRequest([
            'status' => 'In Progress', 'assignedto' => $firstMis->id, 'queued_at' => now(),
        ]);

        $response = $this->actingAs($dispatcher)
            ->post(route('jobrequests.dispatch.action', $jobRequest->id), ['assignedto' => $secondMis->id]);

        $response->assertSessionHasNoErrors();
        $jobRequest->refresh();
        $this->assertSame('In Progress', $jobRequest->status);
        $this->assertSame($secondMis->id, $jobRequest->assignedto);

        $this->assertDatabaseHas('itjr_tracking_logs', [
            'it_job_request_id' => $jobRequest->id,
            'status'            => 'Dispatch Reassigned',
        ]);
    }

    // ── OCD approval feeds into 'Pending Dispatch' ──────────────────────────

    public function test_ocd_in_app_approval_transitions_request_to_pending_dispatch(): void
    {
        $ocd = $this->userWithRoleAndPermission('OCD', 'it.requests.manage');

        $jobRequest = ITJobRequest::create([
            'user_id' => User::factory()->create()->id,
            'category' => 'Hardware', 'title' => 'x', 'description' => 'x',
            'status' => 'Pending OCD Approval', 'divisionchief_id' => $ocd->id,
        ]);

        $this->actingAs($ocd)->post(route('job-requests.ocd-action', $jobRequest->id), [
            'action' => 'approve',
        ]);

        $this->assertSame('Pending Dispatch', $jobRequest->refresh()->status);
    }

    // ── Target completion date: propose (MIS) -> decide (OCD/KID Chief) -> act (MIS) ──

    protected function inProgressRequest(User $assignee, array $overrides = []): ITJobRequest
    {
        return $this->pendingDispatchRequest(array_merge([
            'status'     => 'In Progress',
            'assignedto' => $assignee->id,
            'queued_at'  => now(),
        ], $overrides));
    }

    public function test_propose_target_date_requires_the_assignee_and_the_manage_permission(): void
    {
        $mis = $this->misUser();
        $otherMis = $this->misUser();
        $jobRequest = $this->inProgressRequest($mis);

        // Not the assignee, even though they have the permission via role seeding elsewhere.
        $this->actingAs($otherMis)
            ->put(route('job-requests.propose-target-date', $jobRequest->id), [
                'expected_completion_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertForbidden();
    }

    public function test_assignee_can_propose_a_target_date(): void
    {
        Mail::fake();
        $mis = $this->userWithRoleAndPermission('MIS', 'it.requests.manage');
        $jobRequest = $this->inProgressRequest($mis);
        $date = now()->addDays(3)->toDateString();

        $response = $this->actingAs($mis)
            ->put(route('job-requests.propose-target-date', $jobRequest->id), [
                'expected_completion_date' => $date,
                'mis_assessment'           => 'Looks like a driver issue.',
            ]);

        $response->assertSessionHasNoErrors();
        $jobRequest->refresh();
        $this->assertSame('Pending Target Date Approval', $jobRequest->status);
        $this->assertStringStartsWith($date, (string) $jobRequest->expected_completion_date);
    }

    public function test_target_date_decision_rejects_when_not_pending(): void
    {
        $ocd = $this->userWithRoleAndPermission('OCD', 'it.requests.ocd-approve');
        $mis = $this->misUser();
        $jobRequest = $this->inProgressRequest($mis); // still 'In Progress', not proposed yet

        $response = $this->actingAs($ocd)
            ->post(route('job-requests.target-date-decision', $jobRequest->id), ['action' => 'approve']);

        $response->assertSessionHasErrors('message');
    }

    public function test_ocd_approving_target_date_unblocks_the_act_endpoint(): void
    {
        Mail::fake();
        $mis = $this->userWithRoleAndPermission('MIS', 'it.requests.manage');
        $ocd = $this->userWithRoleAndPermission('OCD', 'it.requests.ocd-approve');
        $jobRequest = $this->inProgressRequest($mis, [
            'status' => 'Pending Target Date Approval',
            'expected_completion_date' => now()->addDays(2)->toDateString(),
        ]);

        $this->actingAs($ocd)
            ->post(route('job-requests.target-date-decision', $jobRequest->id), ['action' => 'approve'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Target Date Approved', $jobRequest->refresh()->status);

        // Now the assignee can act on it.
        $this->actingAs($mis)
            ->put(route('job-requests.update', $jobRequest->id), ['action_taken' => 'Replaced the cable.'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Acted by MIS', $jobRequest->refresh()->status);
    }

    public function test_ocd_rejecting_target_date_requires_a_reason_and_kicks_back_to_mis(): void
    {
        Mail::fake();
        $mis = $this->userWithRoleAndPermission('MIS', 'it.requests.manage');
        $ocd = $this->userWithRoleAndPermission('OCD', 'it.requests.ocd-approve');
        $jobRequest = $this->inProgressRequest($mis, [
            'status' => 'Pending Target Date Approval',
            'expected_completion_date' => now()->addDays(10)->toDateString(),
        ]);

        $this->actingAs($ocd)
            ->post(route('job-requests.target-date-decision', $jobRequest->id), ['action' => 'reject'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($ocd)
            ->post(route('job-requests.target-date-decision', $jobRequest->id), [
                'action' => 'reject', 'reason' => 'Too far out — please expedite.',
            ])
            ->assertSessionHasNoErrors();

        $jobRequest->refresh();
        $this->assertSame('Target Date Rejected', $jobRequest->status);
        $this->assertSame('Too far out — please expedite.', $jobRequest->target_date_rejection_reason);

        // The assignee is blocked from acting until they re-propose and it's approved again.
        $this->actingAs($mis)
            ->put(route('job-requests.update', $jobRequest->id), ['action_taken' => 'x'])
            ->assertSessionHasErrors('message');
    }

    public function test_only_the_assigned_mis_personnel_can_act_on_the_request(): void
    {
        $assignee = $this->userWithRoleAndPermission('MIS', 'it.requests.manage');
        $otherMis = $this->userWithRoleAndPermission('MIS', 'it.requests.manage');
        $jobRequest = $this->inProgressRequest($assignee, ['status' => 'Target Date Approved']);

        $this->actingAs($otherMis)
            ->put(route('job-requests.update', $jobRequest->id), ['action_taken' => 'x'])
            ->assertForbidden();
    }
}
