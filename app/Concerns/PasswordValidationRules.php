<?php

namespace App\Concerns;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function passwordRules(?\App\Models\User $user = null): array
    {
        $minLength = \App\Models\Setting::get('password_min_length', 8);
        $maxLength = \App\Models\Setting::get('password_max_length', 30);
        
        $reqUpper = filter_var(\App\Models\Setting::get('password_require_uppercase', 'true'), FILTER_VALIDATE_BOOLEAN);
        $reqLower = filter_var(\App\Models\Setting::get('password_require_lowercase', 'true'), FILTER_VALIDATE_BOOLEAN);
        $reqNum = filter_var(\App\Models\Setting::get('password_require_numeric', 'true'), FILTER_VALIDATE_BOOLEAN);
        $reqSpecial = filter_var(\App\Models\Setting::get('password_require_special_character', 'true'), FILTER_VALIDATE_BOOLEAN);
        $specialChars = \App\Models\Setting::get('password_special_characters', '!@#$%&');

        $regex = '/^';
        if ($reqLower) $regex .= '(?=.*[a-z])';
        if ($reqUpper) $regex .= '(?=.*[A-Z])';
        if ($reqNum) $regex .= '(?=.*\d)';
        if ($reqSpecial && !empty($specialChars)) {
            $escaped = preg_quote($specialChars, '/');
            $regex .= '(?=.*[' . $escaped . '])';
        }
        $regex .= '.+$/';

        return [
            'required', 
            'string', 
            'min:' . $minLength, 
            'max:' . $maxLength, 
            'regex:' . $regex, 
            'confirmed', 
            new \App\Rules\PasswordHistoryRule($user)
        ];
    }

    /**
     * Get the validation rules used to validate the current password.
     *
     * @return array<int, \Illuminate\Contracts\Validation\Rule|array<mixed>|string>
     */
    protected function currentPasswordRules(): array
    {
        return ['required', 'string', 'current_password'];
    }
}
