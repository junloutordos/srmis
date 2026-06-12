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
}
