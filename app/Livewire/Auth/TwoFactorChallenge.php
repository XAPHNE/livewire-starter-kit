<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Livewire\Component;

class TwoFactorChallenge extends Component
{
    public string $code = '';
    public string $recovery_code = '';
    public bool $showRecoveryInput = false;

    public function mount()
    {
        if (! session()->has('login.id')) {
            return redirect()->route('login');
        }

        $user = \App\Models\User::find(session('login.id'));
        if ($user && $user->two_factor_locked_until && $user->two_factor_locked_until->isFuture()) {
            session()->put('tfa_locked_until', $user->two_factor_locked_until->timestamp);
            session()->forget(['login.id', 'login.remember']);
            return redirect()->route('locked-account');
        } elseif ($user && $user->two_factor_locked_until && $user->two_factor_locked_until->isPast()) {
            $user->update(['two_factor_locked_until' => null, 'two_factor_resend_count' => 0]);
        }
    }

    public function toggleInput()
    {
        $this->showRecoveryInput = ! $this->showRecoveryInput;
        $this->code = '';
        $this->recovery_code = '';
        $this->resetErrorBag();
        
        $this->dispatch('clear-2fa-auth-code');
        
        if (! $this->showRecoveryInput) {
            $this->dispatch('focus-2fa-auth-code');
        }
    }

    public function resendOtp()
    {
        $user = \App\Models\User::find(session('login.id'));

        if (! $user || $user->two_factor_type !== 'email') {
            return;
        }

        if ($user->two_factor_locked_until && $user->two_factor_locked_until->isFuture()) {
            session()->put('tfa_locked_until', $user->two_factor_locked_until->timestamp);
            session()->forget(['login.id', 'login.remember']);
            return redirect()->route('locked-account');
        }

        $maxAttempts = (int) \App\Models\Setting::get('tfa_max_resend_attempts', '3');
        $lockHours = (int) \App\Models\Setting::get('tfa_lockdown_hours', '24');

        if ($user->two_factor_resend_count >= $maxAttempts) {
            $lockUntil = now()->addHours($lockHours);
            $user->update([
                'two_factor_locked_until' => $lockUntil,
            ]);
            
            // Dispatch event to clear session and abort
            session()->forget(['login.id', 'login.remember']);
            session()->put('tfa_locked_until', $lockUntil->timestamp);
            session()->put('lockout_title', 'Account Temporarily Locked');
            session()->put('lockout_reason', 'Your account has been temporarily locked due to multiple OTP resend requests.');
            return redirect()->route('locked-account');
        }

        $user->increment('two_factor_resend_count');
        
        // Generate and send new OTP
        $user->generateTwoFactorCode();
        \Laravel\Fortify\Events\TwoFactorAuthenticationChallenged::dispatch($user);

        $remaining = max(0, $maxAttempts - $user->two_factor_resend_count);
        if ($remaining > 0) {
            flash()->option('position', 'bottom-right')->warning("A new OTP has been sent. You have {$remaining} resend attempts remaining before your account is locked for {$lockHours} hours.");
        } else {
            flash()->option('position', 'bottom-right')->success('A new OTP has been sent to your email.');
        }
    }

    public function verify()
    {
        $this->validate([
            'code' => 'nullable|string',
            'recovery_code' => 'nullable|string',
        ]);

        $user = \App\Models\User::find(session('login.id'));

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->two_factor_type === 'email') {
            if ($user->two_factor_code !== $this->code || $user->two_factor_expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'code' => __('The provided two factor authentication code was invalid.'),
                ]);
            }

            $user->resetTwoFactorCode();

        } else {
            // TOTP Logic
            $validRecoveryCode = false;
            if ($this->recovery_code) {
                $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
                if (is_array($recoveryCodes) && in_array($this->recovery_code, $recoveryCodes)) {
                    $user->replaceRecoveryCode($this->recovery_code);
                    $validRecoveryCode = true;
                }
            }

            if (! $validRecoveryCode) {
                 $provider = app(TwoFactorAuthenticationProvider::class);
                 if (empty($this->code) || ! $provider->verify(decrypt($user->two_factor_secret), $this->code)) {
                     throw ValidationException::withMessages([
                         $this->showRecoveryInput ? 'recovery_code' : 'code' => __('The provided two factor authentication code was invalid.'),
                     ]);
                 }
            }
        }

        $user->update(['two_factor_resend_count' => 0, 'two_factor_locked_until' => null]);
        Auth::login($user, session()->get('login.remember', false));
        session()->forget(['login.id', 'login.remember']);

        return redirect()->intended(config('fortify.home', '/dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.two-factor-challenge')->layout('layouts.auth');
    }
}
