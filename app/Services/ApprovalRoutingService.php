<?php

namespace App\Services;

/**
 * Per-tenant override of which role performs the final ("OCD") approval
 * stage on Vehicle Requests and Facility Requests. OED has no campus
 * director, so FAD Chief is its final approver for both; every other
 * campus keeps OCD. Unlike RoleLabelService (which only changes how a role
 * displays), this changes who actually holds the authority — a real
 * routing decision, paired with the matching permission grants in
 * TenantRolePermissionSeeder.
 *
 * IT Job Requests are NOT covered here — OED's OCD role keeps that duty
 * under its "KID Chief" display label (see TenantRoleLabelsSeeder).
 */
class ApprovalRoutingService
{
    private const FINAL_APPROVER_OVERRIDES = [
        'oed' => 'FAD Chief',
    ];

    /**
     * @param string|null $tenantSlug Overrides tenant('id') — for tests, which
     *                                run without a real tenancy-initialized context.
     */
    public static function finalApproverRole(?string $tenantSlug = null): string
    {
        $slug = $tenantSlug ?? tenant('id');

        return self::FINAL_APPROVER_OVERRIDES[$slug] ?? 'OCD';
    }

    /**
     * True when Facility Requests skip the separate OCD stage entirely —
     * FAD Chief's own approval (fadAction) already is the final approval.
     * Currently true only for OED.
     */
    public static function facilityFinalStageCollapsedIntoFad(?string $tenantSlug = null): bool
    {
        return self::finalApproverRole($tenantSlug) === 'FAD Chief';
    }
}
