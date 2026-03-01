<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class PasswordHistoryRule implements ValidationRule
{
    protected ?User $user;

    public function __construct(?User $user = null)
    {
        $this->user = $user ?? auth()->user();
    }

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!$this->user) {
            return;
        }

        $limit = (int) Setting::get('password_history_limit', 3);

        if ($limit <= 0) {
            return;
        }

        $histories = $this->user->passwordHistories()->latest()->take($limit)->get();

        foreach ($histories as $history) {
            if (Hash::check($value, $history->password_hash)) {
                $fail('The new password cannot be the same as any of your recent passwords.');
                return;
            }
        }
        
        // Also check if matches current password as a redundancy
        if ($this->user->password && Hash::check($value, $this->user->password)) {
            $fail('The new password cannot be the same as your current password.');
            return;
        }
    }
}
