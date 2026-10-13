<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:255'], 'password' => ['required', 'string', 'max:1024']];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['email.required' => 'Ingresa tu correo electrónico.', 'email.email' => 'Ingresa un correo válido.', 'password.required' => 'Ingresa tu contraseña.'];
    }

    public function authenticate(): void
    {
        $key = 'store-login:'.hash('sha256', $this->string('email')->toString().'|'.$this->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Demasiados intentos. Espera un minuto antes de volver a intentar.']);
        }
        if (! Auth::guard('web')->attemptWhen($this->only('email', 'password'), fn (User $user): bool => $user->isActive())) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'No pudimos iniciar sesión con esos datos.']);
        }
        RateLimiter::clear($key);
    }
}
