<?php

namespace Tests\Feature;

use App\Models\Division;
use App\Models\ITJobRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * Staff must confirm & rate an already-acted-on IT Job Request (the CSM
 * survey, CsmResponseController::store) before filing another one — see
 * ITJobRequestController::store() and checkPendingActedByMis().
 */
class ITJobRequestSubmissionTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function requesterWithDivision(): User
    {
        $chief = User::factory()->create();
        $division = Division::create([
            'division_name'     => 'Division of ' . $chief->id,
            'acronym'           => 'D' . $chief->id,
            'division_chief_id' => $chief->id,
            'status'            => 'active',
        ]);

        return User::factory()->create(['division_id' => $division->id]);
    }

    protected function validPayload(): array
    {
        return [
            'category'    => 'Hardware',
            'title'       => 'New request',
            'description' => 'Something broke',
        ];
    }

    public function test_store_is_blocked_while_a_request_is_acted_by_mis_and_unrated(): void
    {
        $requester = $this->requesterWithDivision();

        ITJobRequest::create([
            'user_id'          => $requester->id,
            'category'         => 'Hardware',
            'title'            => 'Broken monitor',
            'description'      => 'Screen flickers',
            'status'           => 'Acted by MIS',
            'divisionchief_id' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($requester)
            ->post(route('jobrequests.store'), $this->validPayload());

        $response->assertSessionHasErrors('message');
        $this->assertDatabaseMissing('it_job_requests', ['title' => 'New request']);
    }

    public function test_store_succeeds_once_the_prior_request_is_rated_or_otherwise_resolved(): void
    {
        $requester = $this->requesterWithDivision();

        ITJobRequest::create([
            'user_id'          => $requester->id,
            'category'         => 'Hardware',
            'title'            => 'Old fixed request',
            'description'      => 'x',
            'status'           => 'Request Completed',
            'divisionchief_id' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($requester)
            ->post(route('jobrequests.store'), $this->validPayload());

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('it_job_requests', ['title' => 'New request', 'user_id' => $requester->id]);
    }

    public function test_check_pending_endpoint_reflects_all_requests_not_just_a_page(): void
    {
        $requester = $this->requesterWithDivision();

        ITJobRequest::create([
            'user_id'          => $requester->id,
            'category'         => 'Hardware',
            'title'            => 'Broken monitor',
            'description'      => 'x',
            'status'           => 'Acted by MIS',
            'divisionchief_id' => User::factory()->create()->id,
        ]);

        $this->actingAs($requester)
            ->getJson(route('jobrequests.check-pending'))
            ->assertOk()
            ->assertJson(['has_pending' => true, 'count' => 1]);
    }

    public function test_superadmin_is_exempt_from_the_gate(): void
    {
        $admin = $this->requesterWithDivision();
        $role = Role::firstOrCreate(['name' => 'Administrator']);
        $admin->roles()->attach($role->id);

        ITJobRequest::create([
            'user_id'          => $admin->id,
            'category'         => 'Hardware',
            'title'            => 'Broken monitor',
            'description'      => 'x',
            'status'           => 'Acted by MIS',
            'divisionchief_id' => User::factory()->create()->id,
        ]);

        $response = $this->actingAs($admin)
            ->post(route('jobrequests.store'), $this->validPayload());

        $response->assertSessionHasNoErrors();
    }
}
