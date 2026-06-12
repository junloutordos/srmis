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
        $domains = array_filter(array_map('trim', explode(',', (string) config('app.allowed_email_domain'))));

        if ($domains !== [] && $request->user()) {
            $email = strtolower($request->user()->email ?? '');

            // Accept each domain itself and any subdomain of it
            // (e.g. crc.pshs.edu.ph under pshs.edu.ph).
            $allowed = false;
            foreach ($domains as $domain) {
                $domain = strtolower($domain);
                if (str_ends_with($email, '@' . $domain) || str_ends_with($email, '.' . $domain)) {
                    $allowed = true;
                    break;
                }
            }

            if (! $allowed) {
                auth()->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Only official PSHS campus accounts may sign in to this system.',
                ]);
            }
        }

        return $next($request);
    }
}
