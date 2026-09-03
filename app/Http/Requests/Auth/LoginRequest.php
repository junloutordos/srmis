<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        // Enforce allowed email domains (same logic as GoogleAuthController)
        $email = strtolower(trim($this->input('email')));
        $domains = array_filter(array_map('trim', explode(',', (string) config('app.allowed_email_domain'))));
        if ($domains) {
            $allowed = false;
            foreach ($domains as $domain) {
                $domain = strtolower($domain);
                if (str_ends_with($email, '@' . $domain) || str_ends_with($email, '.' . $domain)) {
                    $allowed = true;
                    break;
                }
            }
            if (! $allowed) {
                throw ValidationException::withMessages([
                    'email' => 'Only official PSHS accounts are allowed to sign in.',
                ]);
            }
        }

        // Prevent login for users whose status is not active
        $email = $this->input('email');
        $userModel = '\\App\\Models\\User';
        $user = $userModel::where('email', $email)->first();
        if ($user && isset($user->status) && $user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => 'Unable to logged in, contact MIS administrator.',
            ]);
        }

        // No persistent "remember me": tenant identity lives only in the
        // session (bound in AuthenticatedSessionController::store), never in
        // a remember cookie. A remember cookie would let this guard silently
        // re-authenticate the user after the session expires with no tenant
        // resolved, and every `users` table query would then fail.
        if (! Auth::attempt($this->only('email', 'password'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 15)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
