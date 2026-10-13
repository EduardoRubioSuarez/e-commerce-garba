<?php

namespace App\Http\Requests;

use App\Data\ContactData;
use App\Services\PhoneNormalizer;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class StartCheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:150', 'regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'email' => ['bail', 'required', 'string', 'max:255', 'email', 'regex:/\A[^\x00-\x1F\x7F]+\z/u'],
            'phone' => ['bail', 'required', 'string', 'max:64'],
            'country' => ['bail', 'required', 'string', 'size:2', Rule::in(['MX'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'email', 'phone', 'country'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                if (! mb_check_encoding($value, 'UTF-8')) {
                    $this->merge([$field => null]);

                    continue;
                }
                if ($field === 'phone') {
                    continue;
                }
                $normalized = trim($value, ' ');
                $this->merge([$field => match ($field) {
                    'email' => mb_strtolower($normalized),
                    'country' => strtoupper($normalized),
                    default => $normalized,
                }]);
            }
        }
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('phone') || $validator->errors()->has('country')) {
                return;
            }
            try {
                app(PhoneNormalizer::class)->normalize($this->input('phone'), $this->input('country'));
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $validator->errors()->add($field, $message);
                    }
                }
            }
        }];
    }

    public function contactData(): ContactData
    {
        $data = $this->validated();

        return new ContactData(
            name: $data['name'],
            email: $data['email'],
            phoneOriginal: $data['phone'],
            phoneCanonical: app(PhoneNormalizer::class)->normalize($data['phone'], $data['country']),
        );
    }

    /**
     * Flash only contact strings that can be safely persisted in a JSON session.
     */
    protected function failedValidation(ValidatorContract $validator): never
    {
        if ($this->expectsJson()) {
            parent::failedValidation($validator);
        }
        $input = [];
        foreach (['name', 'email', 'phone', 'country'] as $field) {
            $value = $this->input($field);
            $input[$field] = is_string($value) && mb_check_encoding($value, 'UTF-8') ? $value : null;
        }
        $response = redirect($this->getRedirectUrl())->withInput($input)->withErrors($validator->errors(), $this->errorBag);

        throw (new ValidationException($validator, $response))->errorBag($this->errorBag)->redirectTo($this->getRedirectUrl());
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.*' => 'Ingresa un nombre de hasta 150 caracteres, sin caracteres de control.',
            'email.*' => 'Ingresa un correo electrónico válido de hasta 255 caracteres.',
            'phone.*' => 'Ingresa un teléfono de México con 10 dígitos, o +52 seguido de 10 dígitos.',
            'country.*' => 'Selecciona México como país del teléfono.',
        ];
    }
}
