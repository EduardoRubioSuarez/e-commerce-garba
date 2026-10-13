<?php

namespace App\Http\Requests;

class ValidateCouponRequest extends StartCheckoutRequest
{
    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'coupon_code' => ['nullable', 'string', 'max:32', 'regex:/\\A[A-Z0-9_-]+\\z/'],
            'phone' => ['bail', 'required', 'string', 'max:64'],
            'country' => ['bail', 'required', 'in:MX'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();
        if (is_string($this->input('coupon_code'))) {
            $this->merge(['coupon_code' => strtoupper(trim($this->input('coupon_code')))]);
        }
    }
}
