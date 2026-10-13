<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Validation\ValidationException;

class PhoneNormalizer
{
    /**
     * Normalize Mexican contact numbers structurally, without verifying ownership or service.
     *
     * @throws ValidationException
     */
    public function normalize(string $input, string $country): string
    {
        if (strtoupper(trim($country, ' ')) !== 'MX') {
            throw ValidationException::withMessages(['country' => 'Selecciona México como país del teléfono.']);
        }
        $phone = trim($input, ' ');
        if (strlen($input) > 64 || preg_match('/\A(?:\+52[ -]*)?(?:[0-9][0-9 -]*[0-9]|\([0-9]{2,3}\)[ -]*[0-9][0-9 -]*[0-9])\z/', $phone) !== 1) {
            throw $this->invalidPhone();
        }
        $compact = str_replace([' ', '-', '(', ')'], '', $phone);
        $national = str_starts_with($compact, '+52') ? substr($compact, 3) : $compact;
        if (preg_match('/\A[2-9][0-9]{9}\z/', $national) !== 1) {
            throw $this->invalidPhone();
        }

        return '+52'.$national;
    }

    private function invalidPhone(): ValidationException
    {
        return ValidationException::withMessages(['phone' => 'Ingresa un teléfono de México con 10 dígitos, o +52 seguido de 10 dígitos.']);
    }
}
