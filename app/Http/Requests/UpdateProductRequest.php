<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('product')) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['slug'][4] = Rule::unique('products', 'slug')->ignore($this->route('product'));
        $rules['is_active'] = ['required', 'boolean'];
        $rules['is_published'] = ['required', 'boolean'];

        return $rules;
    }
}
