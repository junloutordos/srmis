<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Roles used by the SRMIS modules (MIS, General Services, Data Management,
 * Approval Inbox, Reports, Chat, Digital Signature). Seeded into every
 * tenant (campus) schema.
 */
class TenantRolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Administrator', 'description' => 'Campus system administrator. Bypasses all permission checks.'],
            ['name' => 'MIS',           'description' => 'MIS/IT personnel. Manages IT job requests, ICT equipment, and preventive maintenance.'],
            ['name' => 'DivisionChief', 'description' => 'Division Chief. First-stage approver for division requests.'],
            ['name' => 'FAD Chief',     'description' => 'Finance and Administrative Division Chief. Approves facility, work, and service requests.'],
            ['name' => 'GSU Head',      'description' => 'General Services Unit Head. Assigns drivers and completes work requests.'],
            ['name' => 'OCD',           'description' => 'Office of the Campus Director. Final-stage approver.'],
            ['name' => 'Faculty',       'description' => 'Teaching staff. Files service requests and uses chat.'],
            ['name' => 'Staff',         'description' => 'Non-teaching staff. Files service requests and uses chat.'],
            ['name' => 'Driver',        'description' => 'Campus driver assigned to approved vehicle requests.'],
        ];

        foreach ($roles as $data) {
            Role::updateOrCreate(['name' => $data['name']], ['description' => $data['description']]);
        }

        $this->command?->info('Roles seeded: ' . count($roles));
    }
}
