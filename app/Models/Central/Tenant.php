<?php

namespace App\Models\Central;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * A tenant is one PSHS campus (or the OED). The tenant id is the campus
 * slug (oed, crc, src, ...) and doubles as the schema suffix — the tenant
 * schema name is TENANT_DB_PREFIX + id (e.g. srmis_crc) on the shared
 * MySQL instance.
 *
 * Real columns: id, name, campus_code, created_at, updated_at, data.
 * Everything else (modules map, etc.) lives in the `data` JSON column.
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'campus_code',
        ];
    }

    /*
     * `modules` is a virtual attribute stored in the `data` JSON column by
     * stancl's VirtualColumn trait: a per-tenant activation map such as
     * ['mis' => true, 'general-services' => true, 'chat' => false, ...].
     * Missing keys mean "enabled" (see EnsureModuleEnabled middleware).
     */

    /** The modules SRMIS ships with — used by the wizard / central admin UI. */
    public const MODULES = [
        'mis'               => 'MIS (IT Job Requests, ICT Equipment, PMS, CSM Feedback)',
        'general-services'  => 'General Services (Vehicle, Facility, Work, Service, Messengerial, Assets)',
        'data-management'   => 'Data Management (Users, Roles, Org Structure, Offices, Buildings, Rooms)',
        'approval-inbox'    => 'Unified Approval Inbox',
        'reports'           => 'Reports & Audit Logs',
        'chat'              => 'Chat (real-time messaging)',
        'digital-signature' => 'Digital Signature (PIN-protected e-signatures)',
    ];
}
