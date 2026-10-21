<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * Server-side half of the "set up your digital signature" rule. The overlay in
 * AdminLayout.vue is only a nudge; filing endpoints call this so a request can't
 * be created without a signature image and PIN. Deliberately a per-action check
 * (not middleware) so signed email links and JSON routes are never redirected.
 */
trait RequiresSignatureSetup
{
    protected function signatureSetupRedirect(?User $user): ?RedirectResponse
    {
        if (! $user || $user->isSuperAdmin() || $user->hasSignatureSetup()) {
            return null;
        }

        return back()->withErrors([
            'message' => 'Set up your digital signature and PIN (Profile → Signature) before filing a request.',
        ]);
    }
}
