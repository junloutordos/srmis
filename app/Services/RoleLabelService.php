<?php

namespace App\Services;

use App\Models\Role;
use Illuminate\Support\Facades\Cache;

/**
 * Tenant-facing role labels. Roles are seeded identically into every tenant
 * (see TenantRolesSeeder), but a tenant can override how a role displays —
 * e.g. OED shows its "OCD" role as "KID Chief" everywhere the role name
 * would otherwise be rendered (headings, status text, emails), while the
 * internal role name and every hasRole()/status comparison stay "OCD".
 */
class RoleLabelService
{
    /** @return array<string,string> role name => display label, only entries that actually differ */
    public static function overrides(): array
    {
        if (! tenant()) {
            return [];
        }

        // Cache key is tenant-scoped explicitly, not left to the shared cache
        // prefix bootstrapper — see the note in HandleInertiaRequests about
        // why that isolation can't be trusted across tenants in one process.
        return Cache::remember('role_labels.overrides.' . tenant('id'), 3600, function () {
            return Role::query()
                ->whereNotNull('display_name')
                ->pluck('display_name', 'name')
                ->filter(fn ($label, $name) => $label !== $name)
                ->toArray();
        });
    }

    public static function apply(?string $text): ?string
    {
        if (! $text) {
            return $text;
        }

        foreach (self::overrides() as $name => $label) {
            $text = preg_replace('/\b' . preg_quote($name, '/') . '\b/', $label, $text);
        }

        return $text;
    }
}
