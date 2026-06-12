<?php

namespace App\Tenancy;

use App\Models\Central\Tenant;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single-domain tenant resolution. All campuses share one domain; the tenant
 * is determined per request, in order of precedence:
 *
 *   1. The authenticated session's tenant binding (`tenant_id`, set at login)
 *      — authoritative once logged in. Sessions are stored centrally, so a
 *      tampered/borrowed cookie cannot point the same session at another
 *      campus: the binding travels inside the server-side session.
 *   2. A `?tenant=` request parameter on signed email-approval links — the
 *      parameter is covered by the URL signature, so it cannot be altered.
 *   3. The `email` field of a guest login POST (campus inferred from the
 *      address, e.g. @crc.pshs.edu.ph → crc, @pshssystem.edu.ph → oed).
 *
 * When nothing resolves, the request continues WITHOUT a tenant: guest pages
 * (login, privacy) render fine, and protected routes fall through to the
 * normal auth redirect. Routes that query tenant data are only reachable
 * authenticated, i.e. with a session binding.
 */
class ResolveTenant
{
    public function __construct(protected Tenancy $tenancy)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $this->fromSession($request)
            ?? $this->fromSignedParameter($request)
            ?? $this->fromLoginEmail($request);

        if ($tenant !== null) {
            $this->tenancy->initialize($tenant);
        }

        return $next($request);
    }

    protected function fromSession(Request $request): ?Tenant
    {
        if (! $request->hasSession()) {
            return null;
        }

        $slug = $request->session()->get('tenant_id');

        if (! $slug) {
            return null;
        }

        $tenant = Tenant::find($slug);

        if ($tenant === null) {
            // Tenant was deleted while the session lived — drop the stale auth.
            $request->session()->flush();
        }

        return $tenant;
    }

    protected function fromSignedParameter(Request $request): ?Tenant
    {
        $slug = $request->query('tenant');

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        // Only honor the parameter when the URL signature is intact — the
        // `signed` route middleware aborts later anyway, but never initialize
        // tenancy off a forged value.
        if (! $request->hasValidSignatureWhileIgnoring([])) {
            return null;
        }

        return Tenant::find($slug);
    }

    protected function fromLoginEmail(Request $request): ?Tenant
    {
        if (! $request->isMethod('POST') || $request->user()) {
            return null;
        }

        if (! in_array($request->path(), ['login', 'google/login'], true)) {
            return null;
        }

        $email = $request->input('email');

        return is_string($email) ? CampusEmailMapper::tenantForEmail($email) : null;
    }
}
