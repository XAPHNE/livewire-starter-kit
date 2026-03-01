<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\RateLimiter;
use App\Models\Setting;

class RateLimitForgotPassword
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('password.email') && $request->isMethod('POST')) {
            $maxAttempts = (int) Setting::get('forgot_password_max_attempts', 3);
            $lockHours = (int) Setting::get('forgot_password_lockdown_hours', 24);
            $throttleKey = 'forgot-password|' . $request->ip();

            if (RateLimiter::tooManyAttempts($throttleKey, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($throttleKey);
                session()->put('tfa_locked_until', time() + $seconds);
                session()->put('lockout_title', 'IP Address Temporarily Blocked');
                session()->put('lockout_reason', 'You have been temporarily blocked from requesting password resets due to too many attempts.');
                return redirect()->route('locked-account');
            }

            RateLimiter::hit($throttleKey, $lockHours * 3600);
            
            $retriesLeft = RateLimiter::retriesLeft($throttleKey, $maxAttempts);
            if ($retriesLeft > 0) {
                // Because Fortify normally handles the response internally, we can use the backend notification session directly via helper
                session()->flash('status', "We have emailed your password reset link. You have {$retriesLeft} attempts remaining before a {$lockHours} hour lockout.");
            }
        }

        return $next($request);
    }
}
