<?php

namespace App\Http\Requests\Storefront;

use Illuminate\Foundation\Http\FormRequest;

class ShippingCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'postal_code' => ['required', 'string', 'max:10'],
            'selected_groups' => ['sometimes', 'array'],
            'selected_groups.*' => ['string', 'max:100'],
        ];
    }
}
