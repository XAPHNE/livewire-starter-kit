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

            // 1. Load current session record
            $curr = UserSession::where('session_id', $sid)->where('user_id', $user->id)->first();

            // 2. Self-Healing: If record is missing, try to register it (handles regeneration during login)
            if (! $curr) {
                // Calculate allowed sessions
                $allow = $this->computeAllowedSessions($user);
                
                // Get all active sessions for this user
                $sessions = UserSession::where('user_id', $user->id)
                    ->orderBy('last_activity', 'desc')
                    ->get();

                // If at/over limit, we must evict some to make room for this new session
                if ($sessions->count() >= $allow) {
                    // We need to keep only $allow - 1 sessions to make room for the current one
                    $toKeep = $sessions->take($allow - 1)->pluck('session_id')->toArray();
                    
                    // Purge from custom tracking table
                    UserSession::where('user_id', $user->id)
                        ->whereNotIn('session_id', $toKeep)
                        ->delete();

                    // Purge from Laravel CORE sessions table if database driver is in use
                    if (config('session.driver') === 'database') {
                        \Illuminate\Support\Facades\DB::table(config('session.table'))
                            ->where('user_id', $user->id)
                            ->whereNotIn('id', $toKeep)
                            ->delete();
                    }
                }

                // Register the current (new/regenerated) session
                $curr = UserSession::create([
                    'session_id' => $sid,
                    'user_id' => $user->id,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->header('User-Agent'),
                    'last_activity' => now(),
                ]);
            }

            // 3. Inactivity Enforcement
            $timeout = (int) Setting::get('session_timeout_minutes', 60);
            if ($curr->last_activity) {
                $cutoff = now()->subMinutes($timeout);
                if ($curr->last_activity->lessThan($cutoff)) {
                    Auth::logout();
                    session()->invalidate();
                    session()->regenerateToken();
                    return redirect()->route('login')->with('status', 'Your session has been terminated due to inactivity.');
                }
            }

            // 4. Update last activity
            $curr->update([
                'last_activity' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);
        }

        return $next($request);
    }

    protected function computeAllowedSessions($user): int
    {
        $enabled = filter_var(Setting::get('enable_concurrent_sessions', 'true'), FILTER_VALIDATE_BOOLEAN);
        if (! $enabled) {
            return 1;
        }

        $tierBased = filter_var(Setting::get('enable_tier_based_concurrency', 'false'), FILTER_VALIDATE_BOOLEAN);
        if ($tierBased) {
            // Find the highest session limit from all tiers assigned to the user
            $max = $user->tiers()->whereNotNull('concurrent_sessions')->max('concurrent_sessions');
            
            // If the user has assigned tiers with limits, use the maximum one
            if ($max) return (int) $max;
        }

        // Standard global default
        return (int) Setting::get('default_concurrent_sessions', 1);
    }
}
