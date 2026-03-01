<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth as AuthFacade;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use App\Models\UserSession;
use App\Models\Setting;
use App\Http\Middleware\EnforceSessions;
use App\Mail\SendTwoFactorCodeMail;
use App\Models\User;
use OwenIt\Auditing\AuditableObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Dynamically override session expiration strategy
        config(['session.expire_on_close' => filter_var(Setting::get('session_expire_on_close', 'false'), FILTER_VALIDATE_BOOLEAN)]);
        
        // Register the auditable observer for User model
        User::observe(AuditableObserver::class);

        // Ensure session enforcement middleware is attached to web requests
        if ($this->app->bound('router')) {
            $this->app['router']->pushMiddlewareToGroup('web', EnforceSessions::class);
        }

        // Login: create or update session record and enforce concurrent limits
        Event::listen(Login::class, function (Login $event) {
            /** @var \App\Models\User $user */
            $user = $event->user;
            $sid = session()->getId();

            // 0. Catch-all: Purge expired sessions for this user FIRST to prevent stale records blocking logins
            $timeout = (int) Setting::get('session_timeout_minutes', 60);
            $cutoff = now()->subMinutes($timeout);
            UserSession::where('user_id', $user->getKey())
                ->where('last_activity', '<', $cutoff)
                ->delete();

            UserSession::updateOrCreate(
                ['session_id' => $sid],
                [
                    'user_id' => $user->getKey(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->header('User-Agent'),
                    'last_activity' => now(),
                ]
            );

            // Enforce concurrent sessions by evicting oldest sessions if necessary
            $allow = $this->computeAllowedSessions($user);
            $sessions = UserSession::where('user_id', $user->getKey())
                ->orderBy('last_activity', 'desc')
                ->get();
                
            if ($sessions->count() > $allow) {
                // keep newest $allow sessions
                $toKeep = $sessions->take($allow)->pluck('session_id')->toArray();
                
                // Purge from custom tracking table
                UserSession::where('user_id', $user->getKey())
                    ->whereNotIn('session_id', $toKeep)
                    ->delete();

                // Purge from Laravel CORE sessions table if database driver is in use
                // This is critical for actually logging the user out of other devices
                if (config('session.driver') === 'database') {
                    DB::table(config('session.table'))
                        ->where('user_id', $user->getKey())
                        ->whereNotIn('id', $toKeep)
                        ->delete();
                }
            }
        });

        // Logout: remove session record
        Event::listen(Logout::class, function (Logout $event) {
            $sid = session()->getId();
            UserSession::where('session_id', $sid)->delete();
        });
        Event::listen(function (TwoFactorAuthenticationChallenged $event) {
            $user = $event->user;
            if ($user->two_factor_type === 'email') {
                $user->generateTwoFactorCode();
                Mail::to($user->email)->send(new SendTwoFactorCodeMail($user->two_factor_code));
            }
        });
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
            
            // Otherwise, falling back to global 'default_concurrent_sessions' below
        }

        // Standard global default (also used as fallback for Tier-based system)
        return (int) Setting::get('default_concurrent_sessions', 1);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
