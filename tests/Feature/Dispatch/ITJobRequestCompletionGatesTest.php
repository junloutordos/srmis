<?php

namespace Tests\Feature\Dispatch;

use App\Models\ITJobRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * Per the STRIDE IT-JRF flowchart, only the requestor can close a request,
 * and only after MIS has acted on it; MIS must record an assessment before
 * proposing a target date.
 */
class ITJobRequestCompletionGatesTest extends TestCase
{
    use RefreshesTenantDatabase;

    private function requestFor(User $requester, string $status): ITJobRequest
    {
        return ITJobRequest::create([
            'user_id'          => $requester->id,
            'category'         => 'Hardware',
            'title'            => 'Broken monitor',
            'description'      => 'Screen flickers',
            'status'           => $status,
            'divisionchief_id' => User::factory()->create()->id,
        ]);
    }

    private function csmPayload(ITJobRequest $request): array
    {
        return [
            'respondable_type'    => 'it-job-request',
            'respondable_id'      => $request->id,
            'client_type'         => 'government',
            'region_of_residence' => 'Region IV-A',
            'date_of_transaction' => now()->toDateString(),
            'office_availed'      => 'MIS',
            'service_availed'     => ['IT support'],
            'cc1'                 => 4,
            'sqd0' => 5, 'sqd1' => 5, 'sqd2' => 5, 'sqd3' => 5, 'sqd4' => 5,
            'sqd5' => 5, 'sqd6' => 5, 'sqd7' => 5, 'sqd8' => 5,
        ];
    }

    public function test_only_the_requestor_can_confirm_completion(): void
    {
        $requester = User::factory()->create();
        $request = $this->requestFor($requester, 'Acted by MIS');

        $this->actingAs(User::factory()->create())
            ->post("/it-job-requests/{$request->id}/confirm", ['rating' => 5])
            ->assertForbidden();

        $this->assertSame('Acted by MIS', $request->fresh()->status);
    }

    public function test_confirm_is_refused_before_mis_has_acted(): void
    {
        $requester = User::factory()->create();
        $request = $this->requestFor($requester, 'Pending Dispatch');

        $this->actingAs($requester)
            ->post("/it-job-requests/{$request->id}/confirm", ['rating' => 5])
            ->assertSessionHasErrors('message');

        $this->assertSame('Pending Dispatch', $request->fresh()->status);
    }

    public function test_requestor_can_confirm_after_mis_acted(): void
    {
        $requester = User::factory()->create();
        $request = $this->requestFor($requester, 'Acted by MIS');

        $this->actingAs($requester)
            ->post("/it-job-requests/{$request->id}/confirm", ['rating' => 5])
            ->assertSessionHasNoErrors();

        $this->assertSame('Request Completed', $request->fresh()->status);
    }

    public function test_survey_cannot_close_a_request_before_mis_acted(): void
    {
        $requester = User::factory()->create();
        $request = $this->requestFor($requester, 'Pending Dispatch');

        $this->actingAs($requester)
            ->post(route('csm.store'), $this->csmPayload($request))
            ->assertSessionHasErrors('message');

        $this->assertSame('Pending Dispatch', $request->fresh()->status);
    }

    public function test_survey_completes_a_request_acted_on_by_mis(): void
    {
        $requester = User::factory()->create();
        $request = $this->requestFor($requester, 'Acted by MIS');

        $this->actingAs($requester)
            ->post(route('csm.store'), $this->csmPayload($request))
            ->assertSessionHasNoErrors();

        $this->assertSame('Request Completed', $request->fresh()->status);
    }

    public function test_target_date_proposal_requires_an_assessment(): void
    {
        $mis = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'MIS']);
        $perm = Permission::firstOrCreate(
            ['name' => 'it.requests.manage'],
            ['module' => 'MIS', 'description' => 'Manage ITJR']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $mis->roles()->attach($role->id);

        $request = $this->requestFor(User::factory()->create(), 'In Progress');
        $request->update(['assignedto' => $mis->id]);

        $this->actingAs($mis)
            ->put(route('job-requests.propose-target-date', $request), [
                'expected_completion_date' => now()->addWeek()->toDateString(),
            ])
            ->assertSessionHasErrors('mis_assessment');

        $this->assertSame('In Progress', $request->fresh()->status);

        $this->actingAs($mis)
            ->put(route('job-requests.propose-target-date', $request), [
                'mis_assessment'           => 'Needs a new panel.',
                'expected_completion_date' => now()->addWeek()->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Pending Target Date Approval', $request->fresh()->status);
    }
}
