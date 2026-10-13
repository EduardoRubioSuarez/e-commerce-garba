<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true && $this->user()->isAdministrator();
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:150', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique('categories', 'slug')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['name.required' => 'Ingresa el nombre de la categoría.', 'name.max' => 'El nombre admite hasta 150 caracteres.', 'slug.required' => 'Ingresa el identificador de la categoría.', 'slug.regex' => 'Usa letras minúsculas sin acentos, números y guiones.', 'slug.unique' => 'Ese identificador ya pertenece a otra categoría.', 'slug.max' => 'El identificador admite hasta 150 caracteres.'];
    }
}
