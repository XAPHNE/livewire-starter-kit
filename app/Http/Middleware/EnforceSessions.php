<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth;
use App\Models\UserSession;
use App\Models\Setting;
use Carbon\Carbon;

class EnforceSessions
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() && ! app()->runningUnitTests() && ! app()->environment('testing')) {
            $user = Auth::user();
            $sid = session()->getId();

            // load current session record (if any) BEFORE updating last_activity
            $curr = UserSession::where('session_id', $sid)->where('user_id', $user->id)->first();

            // 1. Session Eviction: If the record is missing from our tracking table, the user was evicted (by admin or concurrency)
            if (! $curr) {
                Auth::logout();
                session()->invalidate();
                session()->regenerateToken();
                return redirect()->route('login')->with('status', 'Your session has been terminated from another device or by an administrator.');
            }

            // 2. Inactivity Enforcement
            $timeout = (int) Setting::get('session_timeout_minutes', 60);
            if ($curr->last_activity) {
                $cutoff = Carbon::now()->subMinutes($timeout);
                if ($curr->last_activity->lessThan($cutoff)) {
                    // session expired
                    Auth::logout();
                    session()->invalidate();
                    session()->regenerateToken();
                    return redirect()->route('login')->with('status', 'Your session has been terminated due to inactivity.');
                }
            }

            // update or create session row
            UserSession::updateOrCreate(
                ['session_id' => $sid],
                [
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'last_activity' => Carbon::now(),
                ]
            );
        }

        return $next($request);
    }
}
