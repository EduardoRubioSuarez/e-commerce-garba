<?php

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCartRequest extends FormRequest
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
        $wholeNumber = function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_int($value) && ! is_string($value)) {
                $fail($attribute === 'quantity' ? 'La cantidad debe ser un entero positivo.' : 'Selecciona un producto válido.');
            }
        };

        return [
            'action' => ['sometimes', Rule::in(['put', 'add', 'remove'])],
            'return_to' => ['sometimes', Rule::in(['product', 'catalog'])],
            'product_id' => ['bail', 'required', $wholeNumber, 'regex:/^[1-9][0-9]*$/D', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
            'quantity' => ['bail', 'exclude_if:action,remove', 'required', $wholeNumber, 'regex:/^[1-9][0-9]*$/D', 'integer', 'min:1', 'max:'.PHP_INT_MAX],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'quantity.*' => 'La cantidad debe ser un entero positivo dentro del límite permitido.',
            'product_id.*' => 'Selecciona un producto válido.',
            'action.*' => 'La acción solicitada no es válida.',
        ];
    }
}
