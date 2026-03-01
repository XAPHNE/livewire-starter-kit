<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class AllowedCharactersRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!preg_match('/^[\pL\pN\s\-\.\_\']+$/u', $value)) {
            $fail('The :attribute contains invalid characters. Only letters, numbers, spaces, dashes, dots, underscores, and apostrophes are allowed.');
        }
    }
}
