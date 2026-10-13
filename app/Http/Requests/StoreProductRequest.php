<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['required', 'string', 'max:150', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', Rule::unique('products', 'slug')],
            'description' => ['nullable', 'string', 'max:10000'],
            'specifications' => ['nullable', 'string', 'max:10000'],
            'equipment_contents' => ['nullable', 'string', 'max:10000'],
            'capacity_btu' => ['nullable', 'integer', 'min:1000', 'max:200000'],
            'base_cost' => ['bail', 'required', 'string', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/'],
            'is_published' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('base_cost') && $this->baseCostInCents() < 1) {
                $validator->errors()->add('base_cost', 'El costo debe ser mayor que cero.');
            }
        }];
    }

    /** @return array<string, mixed> */
    public function attributesForPersistence(): array
    {
        $attributes = $this->validated();
        unset($attributes['base_cost']);
        $attributes['base_cost_cents'] = $this->baseCostInCents();
        $attributes['is_published'] = $this->boolean('is_published');
        $attributes['is_active'] = $this->boolean('is_active', true);

        return $attributes;
    }

    private function baseCostInCents(): int
    {
        $parts = explode('.', $this->string('base_cost')->toString(), 2);

        return ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0', STR_PAD_RIGHT);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Ingresa el nombre del producto.',
            'name.max' => 'El nombre admite hasta 150 caracteres.',
            'category_id.required' => 'Selecciona una categoría.',
            'category_id.exists' => 'Selecciona una categoría válida.',
            'slug.required' => 'Ingresa el identificador del producto.',
            'slug.regex' => 'Usa letras minúsculas sin acentos, números y guiones.',
            'slug.unique' => 'Ese identificador ya pertenece a otro producto, aunque esté desactivado.',
            'slug.max' => 'El identificador admite hasta 150 caracteres.',
            'base_cost.required' => 'Ingresa el costo de fábrica sin IVA.',
            'base_cost.string' => 'Ingresa el costo como texto decimal.',
            'base_cost.regex' => 'Usa un costo positivo con punto y hasta dos decimales; máximo 9999999999.99.',
            'description.max' => 'La descripción admite hasta 10000 caracteres.',
            'specifications.max' => 'Las características admiten hasta 10000 caracteres.',
            'equipment_contents.max' => 'El contenido admite hasta 10000 caracteres.',
            'capacity_btu.integer' => 'La capacidad debe ser un número entero de BTU.',
            'capacity_btu.min' => 'La capacidad mínima es 1000 BTU.',
            'capacity_btu.max' => 'La capacidad máxima es 200000 BTU.',
            'is_published.boolean' => 'Selecciona un estado de publicación válido.',
            'is_active.boolean' => 'Selecciona un estado de activación válido.',
        ];
    }
}
