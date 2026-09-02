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
            ->post(route('jobrequests.dispatch.action', $jobRequest->id), ['assignedto' => $mis->id]);

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
    }

    public function test_dispatch_to_mis_rejects_a_non_mis_assignee(): void
    {
        $dispatcher = $this->userWithRoleAndPermission('ITJR Dispatcher', 'it.requests.dispatch');
        $jobRequest = $this->pendingDispatchRequest();
        $notMis = User::factory()->create();

        $response = $this->actingAs($dispatcher)
            ->post(route('jobrequests.dispatch.action', $jobRequest->id), ['assignedto' => $notMis->id]);

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
}
