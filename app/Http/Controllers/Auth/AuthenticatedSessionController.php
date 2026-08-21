<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        // Single-domain tenancy: the campus was inferred from the email by the
        // ResolveTenant middleware. No tenant = unrecognised campus address.
        if (! tenant()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => 'Unrecognised campus email. Use your official campus address (e.g. name@crc.pshs.edu.ph) or name@pshssystem.edu.ph for OED.',
            ]);
        }

        $request->authenticate();

        $request->session()->regenerate();

        // Bind the campus to the session — authoritative for every later request.
        $request->session()->put('tenant_id', tenant('id'));

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * Unconditional GET logout used by the "session expired" overlay.
     *
     * The overlay's own request already failed with a stale/mismatched CSRF
     * token, so a normal POST /logout (itself CSRF-protected) would just
     * fail the same way. A GET request sidesteps CSRF entirely, and logging
     * out unconditionally here also avoids the `guest` middleware bouncing
     * "Sign In Again" back to the dashboard when the session's auth guard is
     * still technically valid but the token went stale.
     *
     * Being a GET route makes this a logout-CSRF target (e.g. an <img> tag
     * on a third-party page) — Laravel's CSRF middleware doesn't cover GET,
     * so guard it with a same-origin Referer check instead. A real click on
     * the overlay's own link always carries a same-origin Referer; a request
     * with no Referer or a foreign one just gets sent to the login page
     * without touching the session.
     */
    public function forceLogout(Request $request): RedirectResponse
    {
        $referer = $request->headers->get('referer');
        $sameOrigin = $referer && parse_url($referer, PHP_URL_HOST) === $request->getHost();

        if (! $sameOrigin) {
            return redirect()->route('login');
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
