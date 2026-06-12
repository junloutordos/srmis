<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts authenticated access to the email domain configured for this
 * SRMIS instance (e.g. pshs.edu.ph). The domain is configurable per
 * deployment via ALLOWED_EMAIL_DOMAIN; an empty value disables the check.
 */
class EnsureAllowedEmailDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $domain = config('app.allowed_email_domain');

        if ($domain && $request->user()) {
            $email = strtolower($request->user()->email ?? '');

            // Accept the domain itself and any subdomain of it
            // (e.g. crc.pshs.edu.ph under pshs.edu.ph).
            $allowed = str_ends_with($email, '@' . strtolower($domain))
                || str_ends_with($email, '.' . strtolower($domain));

            if (! $allowed) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => "Only {$domain} accounts may sign in to this system.",
                ]);
            }
        }

        return $next($request);
    }
}
