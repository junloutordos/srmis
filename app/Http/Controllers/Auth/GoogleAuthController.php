<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Redirect to Google OAuth (server-side, no popup).
     */
    public function redirect()
    {
        return Socialite::driver('google')
            ->redirect();
    }

    /**
     * Handle Google OAuth callback.
     */
    public function callback()
    {
        $ip = request()->ip();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            $this->securityLog('warning', 'Socialite Google callback failed', ['ip' => $ip, 'error' => $e->getMessage()]);
            return redirect()->route('login')
                ->with('error', 'Google sign-in failed. Please try again.');
        }

        $email = strtolower(trim($googleUser->getEmail() ?? ''));

        // Enforce the configured email domain (and its subdomains)
        if (! $this->emailDomainAllowed($email)) {
            $this->securityLog('warning', 'Socialite login rejected: disallowed domain', ['email' => $email, 'ip' => $ip]);
            return redirect()->route('login')
                ->with('error', 'Only official ' . config('app.agency_code') . ' accounts (@' . config('app.allowed_email_domain') . ') are allowed.');
        }

        // Single-domain tenancy: the OAuth callback carries no tenant context,
        // so resolve the campus from the Google account's email here.
        if (! tenant()) {
            $tenant = \App\Tenancy\CampusEmailMapper::tenantForEmail($email);

            if ($tenant === null) {
                $this->securityLog('warning', 'Socialite login rejected: no campus for email', ['email' => $email, 'ip' => $ip]);
                return redirect()->route('login')
                    ->with('error', 'Your campus is not yet provisioned on SRMIS. Contact the system administrator.');
            }

            tenancy()->initialize($tenant);
        }

        // Find or create user
        $user = User::where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name'     => $googleUser->getName(),
                'email'    => $email,
                'password' => Hash::make(Str::random(16)),
            ]);
        }

        // Block inactive accounts
        if (isset($user->status) && $user->status !== 'active') {
            $this->securityLog('warning', 'Socialite login rejected: inactive account', ['email' => $email, 'ip' => $ip]);
            return redirect()->route('login')
                ->with('error', 'Unable to log in. Contact MIS administrator.');
        }

        Auth::login($user, true);
        request()->session()->regenerate();
        request()->session()->put('tenant_id', tenant('id'));
        $this->securityLog('info', 'Socialite login success', ['email' => $email, 'ip' => $ip, 'role' => $user->role ?? 'staff']);

        $role = $user->role ?? 'staff';

        return redirect($this->getRedirectPath($role));
    }

    /**
     * Firebase popup callback — called by the frontend after Firebase signs in.
     * Security: enforces @crc.pshs.edu.ph domain, blocks inactive accounts,
     * and does NOT create accounts (admin must pre-create users).
     */
    public function login(Request $request)
    {
        $request->validate([
            'email'   => 'required|email',
            'name'    => 'required|string',
            'uid'     => 'required|string',
            'idToken' => 'required|string',
        ]);

        $email = strtolower(trim($request->email));

        // Verify the Firebase ID token server-side so the email cannot be spoofed.
        // Use the Factory directly with the config path — PHP-FPM strips env vars
        // (clear_env = yes by default), so we cannot rely on FIREBASE_CREDENTIALS.
        try {
            $credentialsPath = config('services.firebase.credentials');
            $firebaseAuth    = (new \Kreait\Firebase\Factory)
                ->withServiceAccount($credentialsPath)
                ->createAuth();
            $verified = $firebaseAuth->verifyIdToken($request->idToken);
            if (strtolower($verified->claims()->get('email')) !== $email) {
                return response()->json(['success' => false, 'message' => 'Token email mismatch.'], 403);
            }
        } catch (\Throwable $e) {
            $this->securityLog('warning', 'Google login rejected: invalid ID token', ['ip' => request()->ip(), 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Invalid Google token. Please try again.'], 403);
        }

        $ip = request()->ip();

        // Enforce the configured email domain (and its subdomains)
        if (! $this->emailDomainAllowed($email)) {
            $this->securityLog('warning', 'Google login rejected: disallowed domain', ['email' => $email, 'ip' => $ip]);
            return response()->json(['success' => false, 'message' => 'Only official ' . config('app.agency_code') . ' accounts are allowed.'], 403);
        }

        // Single-domain tenancy: ResolveTenant inferred the campus from the
        // posted email; null means the address maps to no provisioned campus.
        if (! tenant()) {
            $this->securityLog('warning', 'Google login rejected: no campus for email', ['email' => $email, 'ip' => $ip]);
            return response()->json(['success' => false, 'message' => 'Your campus is not yet provisioned on SRMIS. Contact the system administrator.'], 403);
        }

        // User must already exist — no auto-creation via this endpoint
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->securityLog('warning', 'Google login rejected: account not found', ['email' => $email, 'ip' => $ip]);
            return response()->json(['success' => false, 'message' => 'Account not found. Contact MIS administrator.'], 403);
        }

        if (isset($user->status) && $user->status !== 'active') {
            $this->securityLog('warning', 'Google login rejected: inactive account', ['email' => $email, 'ip' => $ip]);
            return response()->json(['success' => false, 'message' => 'Unable to log in. Contact MIS administrator.'], 403);
        }

        // Persist the Google profile photo URL if the user hasn't uploaded a custom one.
        // The Firebase verified token includes a 'picture' claim with the Google photo URL.
        // storageUrl() in the frontend passes http URLs through as-is, so storing them
        // directly in profile_picture is safe and avoids a new migration.
        $googlePhoto = $verified->claims()->get('picture');
        if ($googlePhoto && (! $user->profile_picture || str_starts_with($user->profile_picture, 'https://lh'))) {
            $user->profile_picture = $googlePhoto;
            $user->save();
        }

        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->put('tenant_id', tenant('id'));
        $this->securityLog('info', 'Google login success', ['email' => $email, 'ip' => $ip, 'role' => $user->role ?? 'staff']);

        $role         = $user->role ?? 'staff';
        $redirectPath = $this->getRedirectPath($role);

        return response()->json([
            'success'     => true,
            'redirect_to' => $redirectPath,
        ]);
    }

    /**
     * Write to the security log channel, falling back to the default logger
     * if the security log file cannot be opened (e.g. wrong ownership from a
     * root-run cron job creating the daily file first).
     */
    protected function securityLog(string $level, string $message, array $context = []): void
    {
        try {
            Log::channel('security')->{$level}($message, $context);
        } catch (\Throwable $e) {
            Log::{$level}('[security] ' . $message, $context);
        }
    }

    /**
     * Map role → redirect path.
     */
    protected function getRedirectPath(string $role): string
    {
        return match ($role) {
            'Administrator' => '/admin/dashboard',
            'teacher'       => '/teacher/home',
            'student'       => '/student/home',
            default         => '/dashboard',
        };
    }

    /**
     * Accepts the configured domain and any subdomain of it
     * (pshs.edu.ph allows @pshs.edu.ph and @crc.pshs.edu.ph).
     */
    private function emailDomainAllowed(string $email): bool
    {
        $domains = array_filter(array_map('trim', explode(',', (string) config('app.allowed_email_domain'))));

        if ($domains === []) {
            return true;
        }

        $email = strtolower($email);

        foreach ($domains as $domain) {
            $domain = strtolower($domain);
            if (str_ends_with($email, '@' . $domain) || str_ends_with($email, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }
}
