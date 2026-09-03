<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Per-tenant overrides of how a role's name displays. Roles themselves stay
 * identical across every tenant (TenantRolesSeeder) — this only changes the
 * label. OED has no campus director, so its "OCD" role holder is titled
 * "KID Chief"; every other campus keeps seeing "OCD".
 */
class TenantRoleLabelsSeeder extends Seeder
{
    private const LABELS = [
        'oed' => [
            'OCD' => 'KID Chief',
        ],
    ];

    public function run(): void
    {
        $slug = tenant('id');
        $labels = self::LABELS[$slug] ?? [];

        if (! $labels) {
            return;
        }

        foreach ($labels as $roleName => $displayName) {
            Role::where('name', $roleName)->update(['display_name' => $displayName]);
        }

        $this->command?->info("Role labels applied for tenant '{$slug}': " . implode(', ', array_keys($labels)));
    }
}
