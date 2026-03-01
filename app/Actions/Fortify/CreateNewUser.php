<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $isEnabled = filter_var(\App\Models\Setting::get('enable_public_registration', 'true'), FILTER_VALIDATE_BOOLEAN);
        
        if (! $isEnabled) {
            abort(403, 'Public user registration is currently disabled by the administrators.');
        }

        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => \Illuminate\Support\Facades\Hash::make($input['password']),
            'created_by' => 1,
            'updated_by' => 1,
        ]);

        $user->update([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $defaultRole = \App\Models\Setting::get('default_public_role', '');
        if (! empty($defaultRole) && \Spatie\Permission\Models\Role::where('name', $defaultRole)->exists()) {
            $user->assignRole($defaultRole);
        }

        $defaultTier = \App\Models\Setting::get('default_public_tier', '');
        if (! empty($defaultTier)) {
            $user->tiers()->attach($defaultTier);
        }

        return $user;
    }
}
