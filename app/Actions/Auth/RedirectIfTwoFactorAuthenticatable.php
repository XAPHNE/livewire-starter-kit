<?php

namespace App\Actions\Auth;

use Illuminate\Auth\Events\Failed;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\RedirectsIfTwoFactorAuthenticatable;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;

class RedirectIfTwoFactorAuthenticatable implements RedirectsIfTwoFactorAuthenticatable
{
    protected $guard;
    protected $limiter;

    public function __construct(StatefulGuard $guard, LoginRateLimiter $limiter)
    {
        $this->guard = $guard;
        $this->limiter = $limiter;
    }

    public function handle($request, $next)
    {
        if (\App\Models\Setting::get('login_throttle_strategy', 'account') === 'hybrid') {
            $maxAttempts = (int) \App\Models\Setting::get('max_login_attempts', '5');
            $ipKey = 'login_ip_strike|' . $request->ip();

            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($ipKey, $maxAttempts)) {
                $seconds = \Illuminate\Support\Facades\RateLimiter::availableIn($ipKey);
                session()->put('tfa_locked_until', time() + $seconds);
                session()->put('lockout_title', 'IP Address Temporarily Blocked');
                session()->put('lockout_reason', 'Your IP address has been temporarily blocked due to multiple incorrect login attempts.');
                return redirect()->route('locked-account');
            }
        }

        $user = $this->validateCredentials($request);

        if ($user && $user->hasEnabledTwoFactorAuthentication()) {
            return $this->twoFactorChallengeResponse($request, $user);
        }

        return $next($request);
    }

    protected function validateCredentials($request)
    {
        if (Fortify::$authenticateUsingCallback) {
            return tap(call_user_func(Fortify::$authenticateUsingCallback, $request), function ($user) use ($request) {
                if (! $user) {
                    $this->fireFailedEvent($request);
                    $this->throwFailedAuthenticationException($request);
                }
            });
        }

        $provider = $this->guard->getProvider();

        $user = $provider->retrieveByCredentials($request->only(Fortify::username(), 'password'));

        if ($user) {
            // Check if user is locked (TFA lock or login lock)
            $isTfaLocked = $user->two_factor_locked_until && $user->two_factor_locked_until->isFuture();
            $isLoginLocked = $user->login_locked_until && $user->login_locked_until->isFuture();
            
            if ($isTfaLocked || $isLoginLocked) {
                $lockedUntil = $isTfaLocked ? $user->two_factor_locked_until : $user->login_locked_until;
                session()->put('tfa_locked_until', $lockedUntil->timestamp);
                throw new \Illuminate\Http\Exceptions\HttpResponseException(redirect()->route('locked-account'));
            }

            if (! $provider->validateCredentials($user, ['password' => $request->password])) {
                $this->incrementLoginAttempts($user);
                $this->fireFailedEvent($request, $user);
                $this->throwFailedAuthenticationException($request, $user);
            }

            // Success, reset login attempts
            $user->update(['login_attempts' => 0, 'login_locked_until' => null]);
            
            if (\App\Models\Setting::get('login_throttle_strategy', 'account') === 'hybrid') {
                \Illuminate\Support\Facades\RateLimiter::clear('login_ip_strike|' . $request->ip());
            }

            if (config('hashing.rehash_on_login', true) && method_exists($provider, 'rehashPasswordIfRequired')) {
                $provider->rehashPasswordIfRequired($user, ['password' => $request->password]);
            }

            return $user;
        }

        $this->fireFailedEvent($request);
        $this->throwFailedAuthenticationException($request);

        return null;
    }

    protected function incrementLoginAttempts($user)
    {
        // If the user's lock had expired naturally, reset their attempts before incrementing again
        if ($user->login_locked_until && $user->login_locked_until->isPast()) {
            $user->update(['login_attempts' => 0, 'login_locked_until' => null]);
            $user->refresh();
        }

        $maxAttempts = (int) \App\Models\Setting::get('max_login_attempts', '5');
        $lockHours = (int) \App\Models\Setting::get('login_lockdown_hours', '2');

        if ($user->login_attempts >= $maxAttempts - 1) {
            $lockUntil = now()->addHours($lockHours);
            $user->update([
                'login_locked_until' => $lockUntil,
                'login_attempts' => $user->login_attempts + 1,
            ]);
            
            session()->put('tfa_locked_until', $lockUntil->timestamp);
            session()->put('lockout_title', 'Account Temporarily Locked');
            session()->put('lockout_reason', 'Your account has been temporarily locked due to multiple incorrect login attempts.');
            throw new \Illuminate\Http\Exceptions\HttpResponseException(redirect()->route('locked-account'));
        }

        $user->increment('login_attempts');
    }

    protected function throwFailedAuthenticationException($request, $user = null)
    {
        $this->limiter->increment($request);

        $maxAttempts = (int) \App\Models\Setting::get('max_login_attempts', '5');
        $lockHours = (int) \App\Models\Setting::get('login_lockdown_hours', '2');
        $strategy = \App\Models\Setting::get('login_throttle_strategy', 'account');

        $message = trans('auth.failed');
        $warnings = [];

        if ($user && $user->login_attempts > 0) {
            $remaining = max(0, $maxAttempts - $user->login_attempts);
            if ($remaining > 0) {
                $warnings[] = "Account: {$remaining} attempts left.";
            }
        }

        if ($strategy === 'hybrid') {
            $ipKey = 'login_ip_strike|' . $request->ip();
            \Illuminate\Support\Facades\RateLimiter::hit($ipKey, $lockHours * 3600);
            
            $retriesLeft = \Illuminate\Support\Facades\RateLimiter::retriesLeft($ipKey, $maxAttempts);
            if ($retriesLeft > 0) {
                $warnings[] = "IP: {$retriesLeft} attempts left.";
            }
        }

        if (count($warnings) > 0) {
            $message .= " " . implode(" | ", $warnings);
        }

        throw ValidationException::withMessages([
            Fortify::username() => [$message],
        ]);
    }

    protected function fireFailedEvent($request, $user = null)
    {
        event(new Failed($this->guard?->name ?? config('fortify.guard'), $user, [
            Fortify::username() => $request->{Fortify::username()},
            'password' => $request->password,
        ]));
    }

    protected function twoFactorChallengeResponse($request, $user)
    {
        $request->session()->put([
            'login.id' => $user->getKey(),
            'login.remember' => $request->boolean('remember'),
        ]);

        TwoFactorAuthenticationChallenged::dispatch($user);

        return $request->wantsJson()
            ? response()->json(['two_factor' => true])
            : redirect()->route('two-factor.login');
    }
}
