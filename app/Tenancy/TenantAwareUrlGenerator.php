<?php

namespace App\Tenancy;

use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Arr;

/**
 * Adds the current tenant as a `tenant` query parameter to every signed URL.
 *
 * With single-domain tenancy, signed email-approval links must carry the
 * campus so the request can resolve its tenant without a session. The
 * parameter is covered by the signature, so it cannot be tampered with —
 * ResolveTenant only honors it when the signature validates.
 */
class TenantAwareUrlGenerator extends UrlGenerator
{
    public function signedRoute($name, $parameters = [], $expiration = null, $absolute = true)
    {
        $parameters = Arr::wrap($parameters);

        if (function_exists('tenant') && tenant() && ! isset($parameters['tenant'])) {
            $parameters['tenant'] = tenant()->getTenantKey();
        }

        return parent::signedRoute($name, $parameters, $expiration, $absolute);
    }
}
