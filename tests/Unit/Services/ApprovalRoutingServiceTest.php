<?php

namespace Tests\Unit\Services;

use App\Services\ApprovalRoutingService;
use Tests\TestCase;

/**
 * OED has no campus director, so FAD Chief takes over the final approval
 * stage on Vehicle Requests and Facility Requests. Every other campus keeps
 * OCD. See TenantRolePermissionSeeder for the matching permission grants.
 */
class ApprovalRoutingServiceTest extends TestCase
{
    public function test_oed_final_approver_is_fad_chief(): void
    {
        $this->assertSame('FAD Chief', ApprovalRoutingService::finalApproverRole('oed'));
    }

    public function test_every_other_tenant_final_approver_is_ocd(): void
    {
        $this->assertSame('OCD', ApprovalRoutingService::finalApproverRole('crc'));
        $this->assertSame('OCD', ApprovalRoutingService::finalApproverRole('bag'));
    }

    public function test_no_tenant_context_defaults_to_ocd(): void
    {
        // The value tenant('id') resolves to when tenancy isn't initialized
        // (e.g. the flat test database) — must not accidentally match 'oed'.
        $this->assertSame('OCD', ApprovalRoutingService::finalApproverRole(null));
    }

    public function test_facility_final_stage_is_collapsed_into_fad_only_for_oed(): void
    {
        $this->assertTrue(ApprovalRoutingService::facilityFinalStageCollapsedIntoFad('oed'));
        $this->assertFalse(ApprovalRoutingService::facilityFinalStageCollapsedIntoFad('crc'));
        $this->assertFalse(ApprovalRoutingService::facilityFinalStageCollapsedIntoFad(null));
    }
}
