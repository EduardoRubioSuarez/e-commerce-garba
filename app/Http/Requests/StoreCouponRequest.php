<?php

namespace App\Http\Requests;

use App\Models\Coupon;
use App\Models\StoreSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isActive() === true && $this->user()?->isAdministrator() === true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $coupon = $this->route('coupon');

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/\A[A-Z0-9_-]+\z/', Rule::unique('coupons', 'code')->ignore($coupon instanceof Coupon ? $coupon->id : null)],
            'kind' => ['required', Rule::in(['percentage', 'fixed'])],
            'value' => ['bail', 'required', 'string', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/'],
            'currency' => ['required', Rule::in([StoreSetting::current()->currency])],
            'starts_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'minimum_base' => ['bail', 'required', 'string', 'regex:/\A[0-9]{1,10}(?:\.[0-9]{1,2})?\z/'],
            'max_uses' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'applies_to_installers' => ['sometimes', 'boolean'],
            'applies_to_manual_prices' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'scope' => ['required', Rule::in(['all', 'selected'])],
            'product_ids' => ['required_if:scope,selected', 'array', 'min:1'],
            'product_ids.*' => ['integer', 'distinct', Rule::exists('products', 'id')],
        ];
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('value')) {
                $value = $this->minor((string) $this->input('value'));
                if ($value < 1 || ($this->input('kind') === 'percentage' && $value > 9000)) {
                    $validator->errors()->add('value', 'Usa un valor positivo; el porcentaje máximo es 90%.');
                }
            }
            if (! $validator->errors()->has('starts_at') && ! $validator->errors()->has('ends_at')
                && $this->filled('starts_at') && $this->filled('ends_at') && $this->input('ends_at') <= $this->input('starts_at')) {
                $validator->errors()->add('ends_at', 'El fin debe ser posterior al inicio.');
            }
        }];
    }

    /** @return array<string, mixed> */
    public function attributesForPersistence(): array
    {
        $data = $this->validated();
        unset($data['product_ids'], $data['minimum_base']);
        $data['value'] = $this->minor($data['value']);
        $data['minimum_base_minor'] = $this->minor((string) $this->validated('minimum_base'));
        foreach (['starts_at', 'ends_at'] as $field) {
            $data[$field] = empty($data[$field]) ? null : CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $data[$field], StoreSetting::current()->timezone)->utc();
        }
        $data['applies_to_installers'] = $this->boolean('applies_to_installers');
        $data['applies_to_manual_prices'] = $this->boolean('applies_to_manual_prices');
        $data['is_active'] = $this->boolean('is_active', true);

        return $data;
    }

    private function minor(string $value): int
    {
        $parts = explode('.', $value, 2);

        return (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0', STR_PAD_RIGHT);
    }
}
