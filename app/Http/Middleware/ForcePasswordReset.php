<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForcePasswordReset
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (\Illuminate\Support\Facades\Auth::check()) {
            $user = $request->user();
            $needsReset = $user->reset_password;
            $isExpired = false;

            if (!$needsReset) {
                $expiryDays = (int) \App\Models\Setting::get('password_expiry_days', 90);
                if ($expiryDays > 0) {
                    $lastPasswordChange = $user->passwordHistories()->latest()->first()?->created_at ?? $user->created_at;
                    if ($lastPasswordChange && $lastPasswordChange->addDays($expiryDays)->isPast()) {
                        $needsReset = true;
                        $isExpired = true;
                    }
                }
            }

            if ($needsReset) {
                // Allow them to navigate to the password reset page, logout, or submit new passwords.
                $allowedRoutes = ['user-password.edit', 'logout', 'livewire.update', 'livewire.message'];
                
                if (! in_array($request->route()?->getName(), $allowedRoutes)) {
                    $message = $isExpired 
                        ? 'Your password has expired. You must update it to proceed further.' 
                        : 'You must update your password before proceeding further.';
                    flash()->option('position', 'top-center')->warning($message);
                    return redirect()->route('user-password.edit');
                }
            }
        }

        return $next($request);
    }
}
