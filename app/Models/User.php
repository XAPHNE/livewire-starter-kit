<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use OwenIt\Auditing\Contracts\Auditable;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use Rappasoft\LaravelAuthenticationLog\Traits\AuthenticationLoggable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements Auditable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, SoftDeletes, Notifiable, TwoFactorAuthenticatable, HasRoles, WithExport, AuthenticationLoggable, \OwenIt\Auditing\Auditable;

    protected static function booted(): void
    {
        static::saved(function (User $user) {
            if ($user->wasChanged('password') || $user->wasRecentlyCreated) {
                $user->passwordHistories()->create([
                    'password_hash' => $user->password,
                ]);

                // Clear all other sessions except the current one
                if ($user->wasChanged('password')) {
                    $currentSessionId = session()->getId();
                    
                    \App\Models\UserSession::where('user_id', $user->id)
                        ->where('session_id', '!=', $currentSessionId)
                        ->delete();

                    if (config('session.driver') === 'database') {
                        \Illuminate\Support\Facades\DB::table(config('session.table'))
                            ->where('user_id', $user->id)
                            ->where('id', '!=', $currentSessionId)
                            ->delete();
                    }
                }
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'reset_password',
        'created_by',
        'updated_by',
        'deleted_by',
        'two_factor_type',
        'two_factor_code',
        'two_factor_expires_at',
        'two_factor_resend_count',
        'two_factor_locked_until',
        'login_attempts',
        'login_locked_until',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'two_factor_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'reset_password' => 'boolean',
            'two_factor_expires_at' => 'datetime',
            'two_factor_locked_until' => 'datetime',
            'login_locked_until' => 'datetime',
        ];
    }

    /**
     * Override TwoFactorAuthenticatable's default method.
     * Every user has two-factor authentication enabled (Email OTP by default).
     */
    public function hasEnabledTwoFactorAuthentication()
    {
        if ($this->two_factor_type === 'disabled') {
            return false;
        }

        if ($this->two_factor_type === 'totp') {
            return ! is_null($this->two_factor_secret);
        }
        
        return true;
    }

    /**
     * Generate an SMS/Email OTP code
     */
    public function generateTwoFactorCode()
    {
        $this->timestamps = false;
        $this->two_factor_code = rand(100000, 999999);
        $this->two_factor_expires_at = now()->addMinutes(10);
        $this->save();
    }

    /**
     * Reset the OTP code
     */
    public function resetTwoFactorCode()
    {
        $this->timestamps = false;
        $this->two_factor_code = null;
        $this->two_factor_expires_at = null;
        $this->save();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by')->withTrashed();
    }

    public function createdUsers(): HasMany
    {
        return $this->hasMany(User::class, 'created_by');
    }

    public function updatedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'updated_by');
    }

    public function deletedUsers(): HasMany
    {
        return $this->hasMany(User::class, 'deleted_by');
    }

    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    /**
     * Tiers assigned to the user (many-to-many)
     */
    public function tiers()
    {
        return $this->belongsToMany(Tier::class, 'tier_user');
    }

}
