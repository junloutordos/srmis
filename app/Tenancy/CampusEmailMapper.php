<?php

namespace App\Tenancy;

use App\Models\Central\Tenant;

/**
 * Maps a PSHS email address to its campus tenant.
 *
 * Rules:
 *   - @pshssystem.edu.ph            → oed   (Office of the Executive Director)
 *   - @<slug>.pshs.edu.ph           → <slug> (e.g. @crc.pshs.edu.ph → crc)
 *   - anything else                 → null (not a recognised campus address)
 *
 * The OED special-case domain is configurable via OED_EMAIL_DOMAIN.
 */
class CampusEmailMapper
{
    public static function slugForEmail(string $email): ?string
    {
        $email = strtolower(trim($email));

        if (! str_contains($email, '@')) {
            return null;
        }

        $domain = substr($email, strrpos($email, '@') + 1);

        // OED uses its own domain rather than a pshs.edu.ph subdomain.
        if ($domain === strtolower((string) config('app.oed_email_domain', 'pshssystem.edu.ph'))) {
            return 'oed';
        }

        // Campus pattern: <slug>.pshs.edu.ph
        $base = strtolower((string) config('app.campus_email_base_domain', 'pshs.edu.ph'));

        if (str_ends_with($domain, '.' . $base)) {
            $slug = substr($domain, 0, -strlen('.' . $base));

            // Single label only (crc, mc, cbzrc, ...) — no nested subdomains.
            if ($slug !== '' && ! str_contains($slug, '.')) {
                return $slug;
            }
        }

        return null;
    }

    /** Resolve to a provisioned tenant, or null. */
    public static function tenantForEmail(string $email): ?Tenant
    {
        $slug = static::slugForEmail($email);

        return $slug ? Tenant::find($slug) : null;
    }
}
