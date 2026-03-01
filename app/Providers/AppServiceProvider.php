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
        try {
            if (! $this->app->runningInConsole() || \Illuminate\Support\Facades\Schema::hasTable('settings')) {
                config(['session.expire_on_close' => filter_var(\App\Models\Setting::get('session_expire_on_close', 'false'), FILTER_VALIDATE_BOOLEAN)]);
            }
        } catch (\Throwable $e) {
            // Table doesn't exist yet or connection failed, skip override
        }
        
        // Register the auditable observer for User model
        User::observe(AuditableObserver::class);

        // Ensure session enforcement middleware is attached to web requests
        if ($this->app->bound('router')) {
            $this->app['router']->pushMiddlewareToGroup('web', EnforceSessions::class);
        }

        // Login: proactive session cleanup
        Event::listen(Login::class, function (Login $event) {
            /** @var \App\Models\User $user */
            $user = $event->user;

            // Purge expired sessions for this user to prevent stale records blocking logins
            $timeout = (int) Setting::get('session_timeout_minutes', 60);
            $cutoff = now()->subMinutes($timeout);
            
            UserSession::where('user_id', $user->getKey())
                ->where('last_activity', '<', $cutoff)
                ->delete();
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
