<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class PhoneCameroon implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Format: 6 ou 9, suivi de 8 chiffres = 9 chiffres total
        if (!preg_match('/^[6-9][0-9]{8}$/', $value)) {
            $fail('Le numéro de téléphone doit être un numéro camerounais valide (9 chiffres, commence par 6 ou 9)');
        }
    }
}