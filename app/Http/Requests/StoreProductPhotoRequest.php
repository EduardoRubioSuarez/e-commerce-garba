<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductPhotoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('update', $this->route('product'));
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'file',
                'image',
                'extensions:jpg,jpeg,png,webp',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
                'dimensions:max_width=6000,max_height=6000',
            ],
            'alt' => ['required', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.required' => 'La foto es obligatoria.',
            'photo.file' => 'El archivo subido no es válido.',
            'photo.image' => 'El archivo debe ser una imagen.',
            'photo.extensions' => 'La imagen debe tener una de las siguientes extensiones: jpg, jpeg, png, webp.',
            'photo.mimes' => 'La imagen debe ser de tipo: jpg, jpeg, png, webp.',
            'photo.mimetypes' => 'El tipo MIME debe ser image/jpeg, image/png o image/webp.',
            'photo.max' => 'La imagen no debe superar los 5120 kilobytes.',
            'photo.dimensions' => 'La imagen no debe superar unas dimensiones de 6000x6000 píxeles.',
            'alt.required' => 'El texto alternativo es obligatorio.',
            'alt.string' => 'El texto alternativo debe ser una cadena de texto.',
            'alt.max' => 'El texto alternativo no debe superar los 255 caracteres.',
            'sort_order.integer' => 'El orden debe ser un número entero.',
            'sort_order.min' => 'El orden debe ser como mínimo 0.',
            'sort_order.max' => 'El orden no debe superar 1000000.',
        ];
    }
}
