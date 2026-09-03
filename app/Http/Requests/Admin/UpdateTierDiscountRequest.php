<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTierDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    public function rules(): array
    {
        return [
            'min_qty' => ['required', 'integer', 'min:1'],
            'discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ];
    }
}
