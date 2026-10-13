<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends StoreCategoryRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules['slug'][4] = Rule::unique('categories', 'slug')->ignore($this->route('category'));

        return $rules;
    }
}
