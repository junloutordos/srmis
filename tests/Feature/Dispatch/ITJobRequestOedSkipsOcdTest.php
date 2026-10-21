<?php

namespace Tests\Feature\Dispatch;

use App\Models\Central\Tenant;
use App\Models\Division;
use App\Models\ITJobRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalRoutingService;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * OED goes Division Chief -> Dispatcher directly (no pre-dispatch OCD stage);
 * every other campus keeps 'Pending OCD Approval'.
 */
class ITJobRequestOedSkipsOcdTest extends TestCase
{
    use RefreshesTenantDatabase;

    private function actAsTenant(?string $slug): void
    {
        tenancy()->tenant = $slug ? new Tenant(['id' => $slug]) : null;
    }

    protected function tearDown(): void
    {
        $this->actAsTenant(null);
        parent::tearDown();
    }

    private function pendingRequest(User $chief): ITJobRequest
    {
        $division = Division::create([
            'division_name'     => 'Division ' . $chief->id,
            'acronym'           => 'D' . $chief->id,
            'division_chief_id' => $chief->id,
            'status'            => 'active',
        ]);
        $requester = User::factory()->create(['division_id' => $division->id]);

        return ITJobRequest::create([
            'user_id'          => $requester->id,
            'category'         => 'Hardware',
            'title'            => 'Broken monitor',
            'description'      => 'Screen flickers',
            'status'           => 'Pending Division Chief Approval',
            'divisionchief_id' => $chief->id,
        ]);
    }

    private function chief(): User
    {
        $chief = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'DivisionChief']);
        $perm = Permission::firstOrCreate(
            ['name' => 'it.requests.manage'],
            ['module' => 'MIS', 'description' => 'Manage ITJR']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $chief->roles()->attach($role->id);

        return $chief;
    }

    public function test_oed_division_chief_approval_goes_straight_to_dispatch(): void
    {
        Mail::fake();
        $this->actAsTenant('oed');
        $chief = $this->chief();
        $request = $this->pendingRequest($chief);

        $this->actingAs($chief)
            ->post(route('job-requests.division-chief-action', $request), ['action' => 'approve', 'pin' => '123456'])
            ->assertSessionHasNoErrors();

        $request->refresh();
        $this->assertSame('Pending Dispatch', $request->status);
        $this->assertNotNull($request->queued_at);
        $this->assertNotNull($request->dc_approval_date);
    }

    public function test_other_campuses_still_wait_for_ocd(): void
    {
        Mail::fake();
        $this->actAsTenant('crc');
        $chief = $this->chief();
        $request = $this->pendingRequest($chief);

        $this->actingAs($chief)
            ->post(route('job-requests.division-chief-action', $request), ['action' => 'approve', 'pin' => '123456'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Pending OCD Approval', $request->fresh()->status);
    }

    public function test_oed_ocd_approve_is_refused_for_a_request_not_pending_ocd(): void
    {
        $this->actAsTenant('oed');
        $chief = $this->chief();
        $request = $this->pendingRequest($chief);
        $request->update(['status' => 'Pending Dispatch']);

        $ocd = User::factory()->create();
        $ocd->roles()->attach(Role::firstOrCreate(['name' => 'OCD'])->id);
        $perm = Permission::firstOrCreate(
            ['name' => 'it.requests.ocd-approve'],
            ['module' => 'MIS', 'description' => 'OCD approve']
        );
        Role::where('name', 'OCD')->first()->permissions()->syncWithoutDetaching([$perm->id]);

        $this->actingAs($ocd)
            ->post(route('job-requests.ocd-action', $request), ['action' => 'approve', 'pin' => '123456'])
            ->assertSessionHasErrors('message');

        $this->assertSame('Pending Dispatch', $request->fresh()->status);
    }

    public function test_routing_flag_is_oed_only(): void
    {
        $this->assertTrue(ApprovalRoutingService::itjrSkipsOcdApproval('oed'));
        $this->assertFalse(ApprovalRoutingService::itjrSkipsOcdApproval('crc'));
        $this->assertFalse(ApprovalRoutingService::itjrSkipsOcdApproval(null));
    }
}
