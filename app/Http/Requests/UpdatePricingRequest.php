<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdatePricingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! $user->isActive() || ! $user->isAdministrator()) {
            return false;
        }

        $product = $this->route('product');
        if ($product !== null) {
            return $user->can('update', $product);
        }

        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('product') !== null) {
            $merges = [];
            foreach (['public_price', 'installer_price'] as $field) {
                if (array_key_exists($field, $this->all())) {
                    $value = $this->input($field);
                    if (is_string($value) && trim($value) === '') {
                        $merges[$field] = null;
                    }
                }
            }

            if ($merges !== []) {
                $this->merge($merges);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        if ($this->route('product') !== null) {
            return [
                'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
                'public_price' => ['sometimes', 'nullable', 'string', 'bail', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/'],
                'installer_price' => ['sometimes', 'nullable', 'string', 'bail', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/'],
            ];
        }

        return [
            'page' => ['sometimes', 'integer', 'min:1', 'max:1000000'],
            'public_markup' => ['bail', 'required', 'string', 'regex:/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/'],
            'installer_markup' => ['bail', 'required', 'string', 'regex:/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/'],
            'tax_rate' => ['bail', 'required', 'string', 'regex:/\A[0-9]{1,8}(?:\.[0-9]{1,2})?\z/'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->route('product') !== null) {
                $hasPublic = array_key_exists('public_price', $this->all());
                $hasInstaller = array_key_exists('installer_price', $this->all());

                if (! $hasPublic && ! $hasInstaller) {
                    $validator->errors()->add('public_price', 'Debe proporcionar al menos un precio (público o instalador).');

                    return;
                }

                foreach (['public_price', 'installer_price'] as $field) {
                    if ($validator->errors()->has($field)) {
                        continue;
                    }

                    if (! array_key_exists($field, $this->all())) {
                        continue;
                    }

                    $value = $this->input($field);
                    if ($value === null) {
                        continue;
                    }

                    if (! is_string($value)) {
                        continue;
                    }

                    $cents = $this->convertToCents($value);
                    if ($cents <= 0) {
                        $validator->errors()->add($field, 'El precio debe ser mayor a 0.');
                    }
                }
            } else {
                foreach (['public_markup', 'installer_markup', 'tax_rate'] as $field) {
                    if ($validator->errors()->has($field)) {
                        continue;
                    }

                    $value = $this->input($field);
                    if (! is_string($value)) {
                        continue;
                    }

                    $basis = $this->convertToBasisPoints($value);
                    if ($basis > 4294967295) {
                        $validator->errors()->add($field, 'El porcentaje no puede exceder 42949672.95.');
                    }
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'public_markup.required' => 'El aumento público es obligatorio.',
            'public_markup.string' => 'El aumento público debe ser una cadena de texto.',
            'public_markup.regex' => 'El formato del aumento público no es válido.',
            'installer_markup.required' => 'El aumento de instalador es obligatorio.',
            'installer_markup.string' => 'El aumento de instalador debe ser una cadena de texto.',
            'installer_markup.regex' => 'El formato del aumento de instalador no es válido.',
            'tax_rate.required' => 'La tasa de impuestos es obligatoria.',
            'tax_rate.string' => 'La tasa de impuestos debe ser una cadena de texto.',
            'tax_rate.regex' => 'El formato de la tasa de impuestos no es válido.',
            'public_price.string' => 'El precio público debe ser una cadena de texto.',
            'public_price.regex' => 'El formato del precio público no es válido.',
            'installer_price.string' => 'El precio de instalador debe ser una cadena de texto.',
            'installer_price.regex' => 'El formato del precio de instalador no es válido.',
        ];
    }

    /**
     * Get validated settings changes.
     *
     * @return array{public_markup_basis_points: int, installer_markup_basis_points: int, tax_basis_points: int}
     */
    public function settingsChanges(): array
    {
        return [
            'public_markup_basis_points' => $this->convertToBasisPoints((string) $this->validated('public_markup')),
            'installer_markup_basis_points' => $this->convertToBasisPoints((string) $this->validated('installer_markup')),
            'tax_basis_points' => $this->convertToBasisPoints((string) $this->validated('tax_rate')),
        ];
    }

    /**
     * Get validated override changes.
     *
     * @return array<string, int|null>
     */
    public function overrideChanges(): array
    {
        $validated = $this->validated();
        $changes = [];

        if (array_key_exists('public_price', $validated)) {
            $value = $validated['public_price'];
            $changes['public'] = ($value === null || (is_string($value) && trim($value) === ''))
                ? null
                : $this->convertToCents($value);
        }

        if (array_key_exists('installer_price', $validated)) {
            $value = $validated['installer_price'];
            $changes['installer'] = ($value === null || (is_string($value) && trim($value) === ''))
                ? null
                : $this->convertToCents($value);
        }

        return $changes;
    }

    /**
     * Convert a decimal percentage string to basis points without float operations.
     */
    protected function convertToBasisPoints(string $value): int
    {
        if (str_contains($value, '.')) {
            [$whole, $fractional] = explode('.', $value, 2);
            $fractionalPad = str_pad(substr($fractional, 0, 2), 2, '0', STR_PAD_RIGHT);

            return ((int) $whole) * 100 + (int) $fractionalPad;
        }

        return ((int) $value) * 100;
    }

    /**
     * Convert a decimal price string to cents without float operations.
     */
    protected function convertToCents(string $value): int
    {
        if (str_contains($value, '.')) {
            [$whole, $fractional] = explode('.', $value, 2);
            $fractionalPad = str_pad(substr($fractional, 0, 2), 2, '0', STR_PAD_RIGHT);

            return ((int) $whole) * 100 + (int) $fractionalPad;
        }

        return ((int) $value) * 100;
    }
}
