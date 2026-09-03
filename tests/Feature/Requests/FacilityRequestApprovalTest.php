<?php

namespace Tests\Feature\Requests;

use App\Models\FacilityRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Tests\Concerns\RefreshesTenantDatabase;
use Tests\TestCase;

/**
 * FacilityRequestController::fadAction()'s approve branch, and bookings().
 *
 * These tests run without a real tenant context (see RefreshesTenantDatabase),
 * which is how every non-OED campus behaves — ApprovalRoutingService's OED
 * override (tested directly in ApprovalRoutingServiceTest) cannot be
 * exercised end-to-end here without a live 'oed' schema, so this only
 * guards the default path these edits must leave unchanged.
 */
class FacilityRequestApprovalTest extends TestCase
{
    use RefreshesTenantDatabase;

    protected function fadChief(): User
    {
        // fadAction() is gated both by route middleware (facilities.fad-approve
        // permission) and, redundantly, by an internal check on the literal
        // `position` text field — both must be satisfied.
        $user = User::factory()->create(['position' => 'FAD Chief']);
        $role = Role::firstOrCreate(['name' => 'FAD Chief']);
        $perm = Permission::firstOrCreate(
            ['name' => 'facilities.fad-approve'],
            ['module' => 'GeneralServices', 'description' => 'facilities.fad-approve']
        );
        $role->permissions()->syncWithoutDetaching([$perm->id]);
        $user->roles()->attach($role->id);

        return $user;
    }

    protected function pendingFadRequest(array $overrides = []): FacilityRequest
    {
        $requester = User::factory()->create();

        return FacilityRequest::create(array_merge([
            'requestor_id' => $requester->id,
            'activity'     => 'Seminar',
            'purpose'      => 'Training',
            'date_start'   => now()->addDay()->format('Y-m-d'),
            'status'       => 'Pending FAD Approval',
        ], $overrides));
    }

    public function test_fad_approve_sets_status_to_approved_not_ocd_approved(): void
    {
        $fad = $this->fadChief();
        $facilityRequest = $this->pendingFadRequest();

        $response = $this->actingAs($fad)->post(
            route('facility-requests.fad-action', $facilityRequest->id),
            ['action' => 'approve']
        );

        $response->assertRedirect();
        $this->assertSame('Approved', $facilityRequest->refresh()->status);
    }

    public function test_fad_decline_sets_status_to_declined(): void
    {
        $fad = $this->fadChief();
        $facilityRequest = $this->pendingFadRequest();

        $this->actingAs($fad)->post(
            route('facility-requests.fad-action', $facilityRequest->id),
            ['action' => 'reject', 'reason' => 'Venue unavailable']
        );

        $facilityRequest->refresh();
        $this->assertSame('Declined', $facilityRequest->status);
        $this->assertSame('Venue unavailable', $facilityRequest->decline_reason);
    }

    public function test_bookings_includes_both_approved_and_ocd_approved(): void
    {
        $approved = $this->pendingFadRequest(['activity' => 'Approved one', 'status' => 'Approved']);
        $ocdApproved = $this->pendingFadRequest(['activity' => 'Fully signed off', 'status' => 'OCD Approved']);
        $this->pendingFadRequest(['activity' => 'Still pending', 'status' => 'Pending FAD Approval']);

        $response = $this->actingAs(User::factory()->create())->getJson(route('facility-requests.bookings'));

        $response->assertOk();
        $ids = collect($response->json())->pluck('id')->unique()->all();

        $this->assertContains($approved->id, $ids);
        $this->assertContains($ocdApproved->id, $ids);
        $this->assertCount(2, $ids);
    }
}
